<?php declare(strict_types = 1);

namespace Tally\Complexity;

use Override;
use Tally\Comparison\TolerancePolicy;
use Tally\Metric;
use function sprintf;

/**
 * The tolerance of the cases compared by their growth, described with the complexity class, so
 * that the report lists each class once.
 */
final class GrowthTolerance implements TolerancePolicy
{

	public function __construct(private string $bound, private TolerancePolicy $tolerance)
	{
	}

	#[Override]
	public function allowedChange(Metric $metric, int $expected): float
	{
		return $this->tolerance->allowedChange($metric, $expected);
	}

	#[Override]
	public function describe(): string
	{
		return sprintf('instructions grow at most as %s from the smallest data set, %s', $this->bound, $this->tolerance->describe());
	}

}
