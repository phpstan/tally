<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Rewriter;

use Tally\Attribute\DataProvider;
use Tally\Attribute\Expected;
use Tally\Attribute\Warmup;

final class SortBench
{

	#[Expected(instructions: '1.000K', cycles: '1.100K', memory: '0B')]
	public function benchSort(): void
	{
	}

	#[Warmup(1)]
	public function benchWithoutExpectations(): void
	{
	}

	#[DataProvider('sizes')]
	#[Expected(dataSet: 'small', instructions: '100')]
	#[Expected(dataSet: 'large', instructions: '10.00K')]
	public function benchSizes(int $size): void
	{
	}

}

final class OtherBench
{

	#[Expected(instructions: '5')]
	public function benchSort(): void
	{
	}

}
