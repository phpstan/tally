<?php declare(strict_types = 1);

namespace PHPStan\Tally\Selection;

use InvalidArgumentException;
use PHPStan\Tally\BenchmarkCase;
use PHPStan\Tally\CaseId;
use PHPStan\Tally\Metric;
use function array_keys;
use function max;
use function min;
use function preg_match;
use function range;
use function sprintf;
use function usort;

/**
 * Splits the cases into shards that take about the same time, so CI can run them on several
 * runners: hands out the cases from the most expensive one, each to the shard with the lowest
 * total so far (longest-processing-time-first scheduling). The cost of a case is its expected
 * instructions; a case without them is usually a new reproducer of something slow, so it costs
 * as much as the most expensive one. The same cases with the same costs always give the same shards.
 */
final class ShardSelector implements CaseSelector
{

	/**
	 * @param int $shard from 1
	 * @throws InvalidArgumentException
	 */
	public function __construct(private int $shard, private int $count)
	{
		if ($count < 1 || $shard < 1 || $shard > $count) {
			throw new InvalidArgumentException(sprintf('Shard %d/%d does not exist, use 1/%d to %d/%d.', $shard, $count, max(1, $count), max(1, $count), max(1, $count)));
		}
	}

	/**
	 * @param string $shard like 2/4
	 * @throws InvalidArgumentException
	 */
	public static function fromString(string $shard): self
	{
		if (preg_match('~^(\d+)/(\d+)$~', $shard, $matches) !== 1) {
			throw new InvalidArgumentException(sprintf('Shard %s is not like 2/4.', $shard));
		}

		return new self((int) $matches[1], (int) $matches[2]);
	}

	/**
	 * @return list<BenchmarkCase> from the most expensive one
	 */
	public function select(array $cases): array
	{
		$known = [];
		foreach ($cases as $case) {
			$instructions = $case->expectations->read()?->get(Metric::Instructions);
			if ($instructions === null) {
				continue;
			}

			$known[] = $instructions;
		}
		$unknownCost = $known === [] ? 1 : max($known);

		$costed = [];
		foreach ($cases as $case) {
			$costed[] = [$case, $case->expectations->read()?->get(Metric::Instructions) ?? $unknownCost];
		}
		usort($costed, static fn (array $a, array $b): int => $b[1] !== $a[1] ? $b[1] <=> $a[1] : CaseId::compare($a[0]->id, $b[0]->id));

		$shards = [];
		$totals = [];
		foreach (range(0, $this->count - 1) as $shard) {
			$shards[$shard] = [];
			$totals[$shard] = 0;
		}
		foreach ($costed as [$case, $cost]) {
			$cheapest = array_keys($totals, min($totals), true)[0];
			$shards[$cheapest][] = $case;
			$totals[$cheapest] += $cost;
		}

		return $shards[$this->shard - 1];
	}

}
