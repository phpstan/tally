<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Rewriter;

use Tally\Attribute\DataProvider;
use Tally\Attribute\Expected;
use Tally\Attribute\ExpectedFor;
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
	#[ExpectedFor('small', instructions: '100', cycles: '120', memory: '0B')]
	#[ExpectedFor(dataSet: 'large', instructions: '10.00K', cycles: '12.00K', memory: '0B')]
	public function benchSizes(int $size): void
	{
	}

}

final class OtherBench
{

	#[Expected(instructions: '5', cycles: '6', memory: '0B')]
	public function benchSort(): void
	{
	}

}
