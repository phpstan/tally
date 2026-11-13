<?php declare(strict_types = 1);

namespace Tally\Selection;

use InvalidArgumentException;
use LogicException;
use Override;
use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Metric;
use function array_fill;
use function array_map;
use function array_sum;
use function max;
use function preg_match;
use function sprintf;
use function usort;
use const PHP_INT_MAX;

/**
 * Splits the cases into shards that take about the same time, so CI can run them on several
 * runners: hands out the cases from the most expensive one, each to the shard with the lowest
 * total so far (longest-processing-time-first scheduling). The cost of a case is its expected
 * instructions; a case without them is usually a new reproducer of something slow, so it costs
 * as much as the most expensive one. The cases of a benchmark compared by their growth go into one
 * shard together, with their base. The same cases with the same costs always give the same shards.
 */
final class ShardSelector implements CaseSelector
{

	/**
	 * @param int $shard from 1
	 * @throws InvalidArgumentException
	 */
	public function __construct(private int $shard, private int $count)
	{
		if ($count < 1) {
			throw new InvalidArgumentException(sprintf('Shard %d/%d does not exist, there must be at least one shard.', $shard, $count));
		}
		if ($shard < 1 || $shard > $count) {
			throw new InvalidArgumentException(sprintf('Shard %d/%d does not exist, use 1/%d to %d/%d.', $shard, $count, $count, $count, $count));
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

		[, $shard, $count] = $matches;

		return new self((int) $shard, (int) $count);
	}

	/**
	 * @return list<BenchmarkCase> from the most expensive one
	 */
	#[Override]
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
		// at least 1, so that cases without expectations still spread over the shards
		$unknownCost = max([1, ...$known]);

		$cost = static fn (BenchmarkCase $case): int => $case->expectations->read()?->get(Metric::Instructions) ?? $unknownCost;

		// a case compared by its growth goes into the shard of its base
		$casesByUnit = [];
		foreach ($cases as $case) {
			$casesByUnit[$case->growth?->base->toString() ?? $case->id->toString()][] = $case;
		}
		$units = [];
		foreach ($casesByUnit as $unitCases) {
			$units[] = [$unitCases, array_sum(array_map($cost, $unitCases))];
		}
		usort($units, static fn (array $a, array $b): int => $b[1] !== $a[1] ? $b[1] <=> $a[1] : CaseId::compare($a[0][0]->id, $b[0][0]->id));

		$costsByShard = array_fill(1, $this->count, []);
		$selected = [];
		foreach ($units as [$unitCases, $unitCost]) {
			// the first of the shards with the lowest total
			$cheapest = null;
			$cheapestTotal = PHP_INT_MAX;
			foreach ($costsByShard as $shard => $costs) {
				$total = array_sum($costs);
				if ($total >= $cheapestTotal) {
					continue;
				}

				$cheapest = $shard;
				$cheapestTotal = $total;
			}

			$costsByShard[$cheapest ?? throw new LogicException('There is no shard.')][] = $unitCost;
			if ($cheapest !== $this->shard) {
				continue;
			}

			$selected = [...$selected, ...$unitCases];
		}

		return $selected;
	}

}
