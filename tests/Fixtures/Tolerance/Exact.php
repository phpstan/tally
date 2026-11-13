<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Tolerance;

use Attribute;
use Override;
use Tally\Comparison\TolerancePolicy;
use Tally\Metric;

/** A policy a project defines itself */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Exact implements TolerancePolicy
{

	#[Override]
	public function allowedChange(Metric $metric, int $expected): float
	{
		return 0.0;
	}

	#[Override]
	public function describe(): string
	{
		return 'no difference';
	}

}
