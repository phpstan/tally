<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Tolerance;

use Tally\Attribute\RelativeTolerance;

#[RelativeTolerance(0.2)]
final class ToleranceBench
{

	public function benchOfTheClass(): void
	{
	}

	#[RelativeTolerance(0.05)]
	public function benchOwn(): void
	{
	}

	#[Exact]
	public function benchCustom(): void
	{
	}

}
