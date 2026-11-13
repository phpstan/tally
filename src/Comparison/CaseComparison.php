<?php declare(strict_types = 1);

namespace Tally\Comparison;

use Tally\CaseId;
use Tally\Expectation\SourceLocation;

/** How one case compares with its expectations */
final class CaseComparison
{

	/**
	 * @param list<MetricComparison> $metrics
	 * @param string|null $failure why the case was not measured
	 * @param bool $acceptable whether tally accept can write its numbers, false for a case compared by its growth
	 */
	public function __construct(
		public readonly CaseId $id,
		public readonly SourceLocation $location,
		public readonly TolerancePolicy $tolerance,
		public readonly array $metrics,
		public readonly ?string $failure,
		public readonly Status $status,
		public readonly bool $acceptable
	)
	{
	}

}
