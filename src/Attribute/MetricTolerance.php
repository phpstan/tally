<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use InvalidArgumentException;
use Override;
use Tally\Comparison\Percent;
use Tally\Comparison\TolerancePolicy;
use Tally\Metric;
use function max;
use function number_format;
use function sprintf;

/**
 * Allows each metric its own share, and the cycles at least a number of cycles: a few cache misses
 * more or less move the cycles of a benchmark of a few thousand instructions by a large share, when
 * the code around it changes which cache lines are warm. The default policy is one of these, see
 * Configuration::create().
 *
 *     #[MetricTolerance(instructions: 0.02, cycles: 0.05, memory: 0.05, cyclesAtLeast: 2000)]
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class MetricTolerance implements TolerancePolicy
{

	/**
	 * @param float $instructions allowed share of instructions
	 * @param float $cycles allowed share of cycles
	 * @param float $memory allowed share of memory
	 * @param int $cyclesAtLeast cycles allowed even when they are more than the share
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		public readonly float $instructions,
		public readonly float $cycles,
		public readonly float $memory,
		public readonly int $cyclesAtLeast
	)
	{
		if ($instructions < 0 || $cycles < 0 || $memory < 0 || $cyclesAtLeast < 0) {
			throw new InvalidArgumentException('#[MetricTolerance] needs zero or more of each metric.');
		}
	}

	#[Override]
	public function allowedChange(Metric $metric, int $expected): float
	{
		return match ($metric) {
			Metric::Instructions => $this->instructions,
			// an expected 0 fails on any cycles anyway, the floor only keeps the division defined
			Metric::Cycles => max($this->cycles, $this->cyclesAtLeast / max(1, $expected)),
			Metric::Memory => $this->memory,
		};
	}

	#[Override]
	public function describe(): string
	{
		return sprintf(
			'±%s instructions, ±%s cycles but at least ±%s, ±%s memory',
			(new Percent($this->instructions))->toString(),
			(new Percent($this->cycles))->toString(),
			number_format($this->cyclesAtLeast),
			(new Percent($this->memory))->toString(),
		);
	}

}
