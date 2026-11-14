<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Discovery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tally\BenchmarkCase;
use Tally\Configuration;
use Tally\Discovery\ArgumentResolver;
use Tally\Discovery\AttributeFactory;
use Tally\Discovery\CaseExpander;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Discovery\ValueTypeCheck;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Tests\Expressions;
use Tally\Tests\Fixtures\Complexity\ComplexityBench;
use function file_get_contents;
use function implode;
use function log;

final class GrowthPointsTest extends TestCase
{

	/**
	 * @return iterable<string, array{string, string, string, array<string, float>}>
	 */
	public static function dataPoints(): iterable
	{
		yield 'the base is the smallest size, not the first data set' => ['benchLinear', 'O(n)', 'ten', ['hundred' => 10.0, 'ten' => 1.0, 'thousand' => 100.0]];
		yield 'a number is the size' => ['benchNumber', 'O(n^2 + 0)', 'two', ['six' => 9.0, 'two' => 1.0]];
		yield 'a float size' => ['benchFloat', 'O(n)', 'half', ['half' => 1.0, 'two' => 4.0]];
		yield 'a path to an array' => ['benchPath', 'O(n log n)', 'small', ['small' => 1.0, 'large' => 16 * log(16) / (4 * log(4))]];
		yield 'a variadic parameter is an array' => ['benchVariadic', 'O(n^2)', 'two', ['two' => 1.0, 'four' => 4.0]];
		yield 'constant' => ['benchConstant', 'O(1)', 'ten', ['hundred' => 1.0, 'ten' => 1.0, 'thousand' => 1.0]];
		yield 'logarithmic' => ['benchLogarithmic', 'O(log n)', 'ten', ['hundred' => log(100) / log(10), 'ten' => 1.0, 'thousand' => log(1000) / log(10)]];
		yield 'polynomial' => ['benchCubic', 'O(n^3)', 'two', ['six' => 27.0, 'two' => 1.0]];
		yield 'a polynomial of degree 1' => ['benchDegreeOne', 'O(n^1)', 'two', ['six' => 3.0, 'two' => 1.0]];
		yield 'exponential' => ['benchExponential', 'O(2^n)', 'two', ['six' => 16.0, 'two' => 1.0]];
	}

	/**
	 * @param array<string, float> $growth by data set
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('dataPoints')]
	public function testPoints(string $method, string $bound, string $base, array $growth): void
	{
		$actual = [];
		foreach (self::expand($method) as $case) {
			$this->assertNotNull($case->growth);
			$this->assertSame($bound, $case->growth->bound);
			$this->assertSame(ComplexityBench::class . '::' . $method . '@' . $base, $case->growth->base->toString());
			$actual[$case->id->dataSet ?? ''] = $case->growth->growth;
		}

		$this->assertSame($growth, $actual);
	}

	/**
	 * @return iterable<string, array{string, list<string>}>
	 */
	public static function dataInvalid(): iterable
	{
		yield 'no data provider' => ['benchWithoutProvider', ['#[Linear] compares data sets of different sizes, but the method has no data provider.']];
		yield 'with #[ExpectedFor]' => ['benchWithExpectedFor', ['#[Linear] checks how the instructions grow and #[ExpectedFor] expects numbers, keep one of them.']];
		yield 'with expectations from the data provider' => ['benchWithDataSetExpectations', ['data set a has expectations from the data provider, but #[Linear] checks how the instructions grow, keep one of them.']];
		yield 'two complexity classes' => ['benchTwoClasses', ['it has more than one complexity class: Tally\Attribute\Linear, Tally\Attribute\Quadratic.']];
		yield 'a size that does not parse' => ['benchSizeSyntax', [
			'#[Linear] size: Expected a whole number or a quoted string as the key, but the expression ends.',
			'  numbers[',
			'          ^',
		]];
		yield 'a size with an unknown variable' => ['benchSizeUnknownVariable', [
			'#[Linear] size: Unknown variable "numbrs". Did you mean "numbers"?',
			'  numbrs',
			'  ^^^^^^',
		]];
		yield 'a size of a type without a size' => ['benchSizeString', ['#[Linear] size name is a string, it must be a number or an array, whose count is the size.']];
		yield 'a formula that does not parse' => ['benchFormulaSyntax', [
			'#[Complexity] formula: Expected an operator, found "log".',
			'  n log(n)',
			'    ^^^',
		]];
		yield 'a formula with an unknown function' => ['benchFormulaUnknownFunction', [
			'#[Complexity] formula: Unknown function "lg". Did you mean "log"?',
			'  n * lg(n)',
			'      ^^',
		]];
		yield 'a formula of another variable' => ['benchFormulaUnknownVariable', [
			'#[Complexity] formula: Unknown variable "m". Did you mean "n"?',
			'  m',
			'  ^',
		]];
		yield 'one data set' => ['benchOneDataSet', ['#[Linear] needs data sets of at least two sizes, the data provider yields 1.']];
		yield 'a size with a key a data set does not have' => ['benchSizeMissingKey', [
			'#[Linear] size in data set small: input has no key "itms". Did you mean "items"?',
			'  input.itms',
			'        ^^^^',
		]];
		yield 'a size of a value without a size' => ['benchSizeValueString', ['#[Linear] size value is a string in data set text, it must be a number or an array, whose count is the size.']];
		yield 'two data sets of the same size' => ['benchSameSize', ['data sets a and c both have size 2, #[Linear] needs each data set to have another size.']];
		yield 'a formula that is 0 at the base' => ['benchZeroAtBase', ['#[Logarithmic] formula log(n) is 0 at size 1 of data set one, it must be above 0 and finite at every size.']];
		yield 'a formula too large for a float' => ['benchInfinite', ['#[Exponential] formula 2^n is INF at size 2000 of data set large, it must be above 0 and finite at every size.']];
		yield 'a formula out of its domain' => ['benchOutOfDomain', [
			'#[Complexity] formula at size 1 of data set one: sqrt() needs a number from 0, n - 5 is -4.',
			'  sqrt(n - 5)',
			'       ^^^^^',
		]];
		yield 'a formula below 0 beyond the base' => ['benchNegative', ['#[Complexity] formula 5 - n is -5 at size 10 of data set ten, it must be above 0 and finite at every size.']];
		yield 'a polynomial of degree 0' => ['benchDegreeZero', ['#[Polynomial] needs a degree from 1, 0 given. Use #[Constant] for 0.']];
	}

	/**
	 * @param list<string> $message
	 */
	#[DataProvider('dataInvalid')]
	public function testInvalid(string $method, array $message): void
	{
		try {
			self::expand($method);
			self::fail('No exception.');
		} catch (InvalidBenchmarkException $e) {
			$this->assertSame(ComplexityBench::class . '::' . $method . '(): ' . implode("\n", $message), $e->getMessage());
		}
	}

	/**
	 * @return list<BenchmarkCase>
	 * @throws InvalidBenchmarkException
	 */
	private static function expand(string $method): array
	{
		$class = new ReflectionClass(ComplexityBench::class);
		$rewriter = new ExpectedAttributeRewriter();
		$expander = new CaseExpander($rewriter, new AttributeFactory(new ValueTypeCheck()), new ArgumentResolver(), (new Expressions())->growthPoints());

		return $expander->expand($class, $class->getMethod($method), $expander->hooks($class), $rewriter->index((string) file_get_contents((string) $class->getFileName())), Configuration::create());
	}

}
