<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\AcceptGrowth;

use Tally\Attribute\Complexity;
use Tally\Attribute\Constant;
use Tally\Attribute\DataProvider;
use Tally\Attribute\Linear;
use Tally\Attribute\Linearithmic;

final class AcceptGrowthBench
{

	public static function sizes(): iterable
	{
		yield 'ten' => [10];
		yield 'twenty' => [20];
		yield 'forty' => [40];
	}

	public static function fromZero(): iterable
	{
		yield 'zero' => [0];
		yield 'one' => [1];
		yield 'two' => [2];
	}

	#[DataProvider('sizes')]
	#[Constant('size')]
	public function benchUp(int $size): void
	{
	}

	#[DataProvider('sizes')]
	#[Linear(size: 'size')]
	public function benchDown(int $size): void
	{
	}

	#[DataProvider('sizes')]
	#[Linearithmic('size')]
	public function benchKept(int $size): void
	{
	}

	#[DataProvider('sizes')]
	#[Complexity(size: 'size', formula: 'n^3')]
	public function benchFormula(int $size): void
	{
	}

	#[DataProvider('fromZero')]
	#[Constant('size')]
	public function benchUnbounded(int $size): void
	{
	}

	#[DataProvider('sizes')]
	#[Constant('size')]
	public function benchNotMeasured(int $size): void
	{
	}

}
