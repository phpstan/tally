<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Complexity;

use Tally\Attribute\Complexity;
use Tally\Attribute\Constant;
use Tally\Attribute\DataProvider;
use Tally\Attribute\Exponential;
use Tally\Attribute\ExpectedFor;
use Tally\Attribute\Linear;
use Tally\Attribute\Linearithmic;
use Tally\Attribute\Logarithmic;
use Tally\Attribute\Polynomial;
use Tally\Attribute\Quadratic;
use Tally\DataSet;
use Tally\Metrics;
use Tally\Tests\InMemoryExpectations;

final class ComplexityBench
{

	/** The larger one first: the base is the smallest, not the first */
	public static function lists(): iterable
	{
		yield 'hundred' => [range(1, 100)];
		yield 'ten' => [range(1, 10)];
		yield 'thousand' => [range(1, 1000)];
	}

	public static function sizes(): iterable
	{
		yield 'six' => [6];
		yield 'two' => [2];
	}

	public static function floats(): iterable
	{
		yield 'half' => [0.5];
		yield 'two' => [2.0];
	}

	public static function inputs(): iterable
	{
		yield 'small' => [['items' => range(1, 4), 'name' => 'a']];
		yield 'large' => [['items' => range(1, 16), 'name' => 'b']];
	}

	public static function mixed(): iterable
	{
		yield 'small' => [[1]];
		yield 'text' => ['abc'];
	}

	public static function one(): iterable
	{
		yield 'only' => [[1]];
	}

	public static function sameSize(): iterable
	{
		yield 'a' => [[1, 2]];
		yield 'b' => [[3]];
		yield 'c' => [[4, 5]];
	}

	public static function withExpectations(): iterable
	{
		yield DataSet::named('a', [[1]])->withExpectations(new InMemoryExpectations(null, __FILE__));
	}

	public static function variadic(): iterable
	{
		yield 'two' => [1, 2];
		yield 'four' => [1, 2, 3, 4];
	}

	public static function ones(): iterable
	{
		yield 'one' => [1];
		yield 'ten' => [10];
	}

	public static function large(): iterable
	{
		yield 'small' => [10];
		yield 'large' => [2000];
	}

	#[DataProvider('lists')]
	#[Linear('numbers')]
	public function benchLinear(array $numbers): void
	{
	}

	#[DataProvider('sizes')]
	#[Complexity(size: 'size', formula: 'n^2 + 0')]
	public function benchNumber(int $size): void
	{
	}

	#[DataProvider('floats')]
	#[Linear('size')]
	public function benchFloat(float $size): void
	{
	}

	#[DataProvider('inputs')]
	#[Linearithmic('input.items')]
	public function benchPath(array $input): void
	{
	}

	#[DataProvider('variadic')]
	#[Quadratic('numbers')]
	public function benchVariadic(int ...$numbers): void
	{
	}

	#[DataProvider('lists')]
	#[Constant('numbers')]
	public function benchConstant(array $numbers): void
	{
	}

	#[DataProvider('lists')]
	#[Logarithmic('numbers')]
	public function benchLogarithmic(array $numbers): void
	{
	}

	#[DataProvider('sizes')]
	#[Polynomial('size', degree: 3)]
	public function benchCubic(int $size): void
	{
	}

	#[DataProvider('sizes')]
	#[Polynomial('size', degree: 1)]
	public function benchDegreeOne(int $size): void
	{
	}

	#[DataProvider('sizes')]
	#[Exponential('size')]
	public function benchExponential(int $size): void
	{
	}

	#[Linear('numbers')]
	public function benchWithoutProvider(): void
	{
	}

	#[DataProvider('lists')]
	#[Linear('numbers')]
	#[ExpectedFor('ten', instructions: '1K', cycles: '1K', memory: '0B')]
	public function benchWithExpectedFor(array $numbers): void
	{
	}

	#[DataProvider('withExpectations')]
	#[Linear('numbers')]
	public function benchWithDataSetExpectations(array $numbers): void
	{
	}

	#[DataProvider('lists')]
	#[Linear('numbers')]
	#[Quadratic('numbers')]
	public function benchTwoClasses(array $numbers): void
	{
	}

	#[DataProvider('lists')]
	#[Linear('numbers[')]
	public function benchSizeSyntax(array $numbers): void
	{
	}

	#[DataProvider('lists')]
	#[Linear('numbrs')]
	public function benchSizeUnknownVariable(array $numbers): void
	{
	}

	#[DataProvider('mixed')]
	#[Linear('name')]
	public function benchSizeString(string $name): void
	{
	}

	#[DataProvider('lists')]
	#[Complexity(size: 'numbers', formula: 'n log(n)')]
	public function benchFormulaSyntax(array $numbers): void
	{
	}

	#[DataProvider('lists')]
	#[Complexity(size: 'numbers', formula: 'n * lg(n)')]
	public function benchFormulaUnknownFunction(array $numbers): void
	{
	}

	#[DataProvider('lists')]
	#[Complexity(size: 'numbers', formula: 'm')]
	public function benchFormulaUnknownVariable(array $numbers): void
	{
	}

	#[DataProvider('one')]
	#[Linear('numbers')]
	public function benchOneDataSet(array $numbers): void
	{
	}

	#[DataProvider('inputs')]
	#[Linear('input.itms')]
	public function benchSizeMissingKey(array $input): void
	{
	}

	#[DataProvider('mixed')]
	#[Linear('value')]
	public function benchSizeValueString(mixed $value): void
	{
	}

	#[DataProvider('sameSize')]
	#[Linear('numbers')]
	public function benchSameSize(array $numbers): void
	{
	}

	#[DataProvider('ones')]
	#[Logarithmic('size')]
	public function benchZeroAtBase(int $size): void
	{
	}

	#[DataProvider('large')]
	#[Exponential('size')]
	public function benchInfinite(int $size): void
	{
	}

	#[DataProvider('ones')]
	#[Complexity(size: 'size', formula: 'sqrt(n - 5)')]
	public function benchOutOfDomain(int $size): void
	{
	}

	#[DataProvider('ones')]
	#[Complexity(size: 'size', formula: '5 - n')]
	public function benchNegative(int $size): void
	{
	}

	#[DataProvider('sizes')]
	#[Polynomial('size', degree: 0)]
	public function benchDegreeZero(int $size): void
	{
	}

}
