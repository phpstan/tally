<?php declare(strict_types = 1);

namespace Tally\Comparison;

use Tally\Metric;

/** One metric of a case: what was expected, what was measured, and how far apart they may be. */
final class MetricComparison
{

	/**
	 * @param float|null $allowedChange null when there is nothing to compare
	 */
	public function __construct(
		public readonly Metric $metric,
		public readonly ?int $expected,
		public readonly ?int $measured,
		public readonly ?float $allowedChange,
		public readonly Status $status
	)
	{
	}

	/** The relative change, null when it has no value to compare with or nothing was expected */
	public function change(): ?float
	{
		if ($this->expected === null || $this->measured === null || $this->expected === 0) {
			return null;
		}

		return ($this->measured - $this->expected) / $this->expected;
	}

}
