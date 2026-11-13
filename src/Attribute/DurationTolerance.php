<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use InvalidArgumentException;
use Override;
use Tally\Comparison\Percent;
use Tally\Comparison\TolerancePolicy;
use Tally\Metric;
use Tally\Quantity\QuantityFormat;
use function array_key_first;
use function array_keys;
use function array_reverse;
use function implode;
use function max;
use function min;
use function round;
use function sort;
use function sprintf;

/**
 * Allows a larger share of change the quicker the benchmark is, for benchmarks that take from
 * milliseconds to seconds, like analysing a whole file. The expected instructions or cycles are
 * converted to the milliseconds they take natively, and the share allowed comes from the range
 * of milliseconds they fall in. Within each range the margin is at least what the range below
 * allows at its upper end, so a benchmark just past a range boundary is not held to less than
 * one just below it, and the quickest benchmarks are allowed the margin of the slowest one in
 * their range. Memory is allowed a fixed share.
 *
 * PHPStan analyses files with:
 *
 *     #[DurationTolerance(
 *         tolerances: [0 => 0.5, 100 => 0.25, 500 => 0.2, 2000 => 0.1],
 *         instructionsPerMillisecond: 5_800_000,
 *         cyclesPerMillisecond: 7_400_000,
 *         memory: 0.1,
 *     )]
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class DurationTolerance implements TolerancePolicy
{

	/**
	 * @param array<int, float> $tolerances allowed share keyed by the milliseconds from which it applies, from 0
	 * @param float $memory allowed share of memory
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		public readonly array $tolerances,
		public readonly int $instructionsPerMillisecond,
		public readonly int $cyclesPerMillisecond,
		public readonly float $memory
	)
	{
		if (array_key_first($tolerances) !== 0) {
			throw new InvalidArgumentException('#[DurationTolerance] needs a tolerance from 0 ms.');
		}
		$starts = array_keys($tolerances);
		$sorted = $starts;
		sort($sorted);
		if ($starts !== $sorted) {
			throw new InvalidArgumentException('#[DurationTolerance] needs the tolerances in ascending order of milliseconds.');
		}
		if ($instructionsPerMillisecond < 1 || $cyclesPerMillisecond < 1) {
			throw new InvalidArgumentException('#[DurationTolerance] needs at least 1 instruction and cycle per millisecond.');
		}
	}

	#[Override]
	public function allowedChange(Metric $metric, int $expected): float
	{
		$perMillisecond = match ($metric) {
			Metric::Instructions => $this->instructionsPerMillisecond,
			Metric::Cycles => $this->cyclesPerMillisecond,
			Metric::Memory => null,
		};
		if ($perMillisecond === null) {
			return $this->memory;
		}

		$expectedMilliseconds = $expected / $perMillisecond;
		$allowedMilliseconds = 0.0;
		foreach ($this->ranges() as [$start, $tolerance, $end]) {
			if ($start > $expectedMilliseconds) {
				break;
			}
			// the quickest range is allowed its margin at its upper end, the others at least that
			$milliseconds = $end === null ? $expectedMilliseconds : ($start === 0 ? $end : min($expectedMilliseconds, $end));
			$allowedMilliseconds = max($allowedMilliseconds, $tolerance * $milliseconds);
		}

		return $allowedMilliseconds / $expectedMilliseconds;
	}

	#[Override]
	public function describe(): string
	{
		$ranges = [];
		foreach ($this->ranges() as [$start, $tolerance, $end]) {
			$ranges[] = $start === 0 && $end !== null
				? sprintf('±%d ms below %d ms', (int) round($tolerance * $end), $end)
				: sprintf('±%s from %d ms', (new Percent($tolerance))->toString(), $start);
		}

		return sprintf(
			'instructions and cycles by the time they take at %s instructions or %s cycles per ms: %s, each at least what the range below allows; ±%s memory',
			QuantityFormat::count()->format($this->instructionsPerMillisecond),
			QuantityFormat::count()->format($this->cyclesPerMillisecond),
			implode(', ', $ranges),
			(new Percent($this->memory))->toString(),
		);
	}

	/**
	 * @return list<array{int, float, int|null}> the start, the tolerance, and the start of the next range
	 */
	private function ranges(): array
	{
		$ranges = [];
		$end = null;
		foreach (array_reverse($this->tolerances, true) as $start => $tolerance) {
			$ranges[] = [$start, $tolerance, $end];
			$end = $start;
		}

		return array_reverse($ranges);
	}

}
