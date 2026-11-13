<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Discovery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tally\BenchmarkCase;
use Tally\Discovery\AttributeFactory;
use Tally\Discovery\CaseExpander;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Discovery\ValueTypeCheck;
use Tally\Expectation\AttributeExpectations;
use Tally\Expectation\ExpectationException;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Tests\Fixtures\Invalid\InvalidBench;
use Tally\Tests\Fixtures\Suite\FilesBench;
use Tally\Tests\Fixtures\Suite\Nested\IndexedBench;
use Tally\Tests\Fixtures\Suite\Nested\LoopBench;
use Tally\Tests\Fixtures\Suite\StringBench;
use Tally\Tests\Fixtures\Tolerance\DefaultBench;
use Tally\Tests\Fixtures\Tolerance\ToleranceBench;
use Tally\Tests\InMemoryExpectations;
use function array_fill;
use function array_map;

final class CaseExpanderTest extends TestCase
{

	/**
	 * @throws InvalidBenchmarkException
	 * @throws ExpectationException
	 */
	public function testMethodWithoutDataProvider(): void
	{
		$case = self::expandOne(StringBench::class, 'benchSprintf');

		$this->assertSame(StringBench::class . '::benchSprintf', $case->id->toString());
		$this->assertSame([], $case->arguments);
		$this->assertSame(0, $case->warmup);
		$this->assertInstanceOf(AttributeExpectations::class, $case->expectations);
		$this->assertSame(['instructions' => 1204, 'cycles' => 1387, 'memory' => 0], $case->expectations->read()?->toArray());
		$this->assertSame(12, $case->expectations->location()->line);
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testWarmupAndNoExpectations(): void
	{
		$case = self::expandOne(StringBench::class, 'benchConcat');

		$this->assertSame(2, $case->warmup);
		$this->assertNull($case->expectations->read());
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testDataProviderKeepsItsOrder(): void
	{
		$cases = self::expand(LoopBench::class, 'benchSum');

		$this->assertSame(['size-1000', 'size-10', 'size-100'], array_map(static fn (BenchmarkCase $case): ?string => $case->id->dataSet, $cases));
		$this->assertSame([[1000], [10], [100]], array_map(static fn (BenchmarkCase $case): array => $case->arguments, $cases));
		$this->assertSame(
			[null, ['instructions' => 100, 'cycles' => 110, 'memory' => 0], ['instructions' => 1000, 'cycles' => 1100, 'memory' => 0]],
			array_map(static fn (BenchmarkCase $case): ?array => $case->expectations->read()?->toArray(), $cases),
		);
		// in declaration order, private ones too
		$this->assertSame(array_fill(0, 3, ['setUp', 'prepare']), array_map(static fn (BenchmarkCase $case): array => $case->beforeMethods, $cases));
		$this->assertSame(array_fill(0, 3, ['tearDown']), array_map(static fn (BenchmarkCase $case): array => $case->afterMethods, $cases));
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testDataSetsOfAList(): void
	{
		$cases = self::expand(IndexedBench::class, 'benchIndexed');

		$this->assertSame(['#0', '#1'], array_map(static fn (BenchmarkCase $case): ?string => $case->id->dataSet, $cases));
		$this->assertSame([[1], [2]], array_map(static fn (BenchmarkCase $case): array => $case->arguments, $cases));
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testDataSetWithItsOwnExpectations(): void
	{
		$cases = self::expand(FilesBench::class, 'benchFile');

		$this->assertSame(['b.php', 'a.php'], array_map(static fn (BenchmarkCase $case): ?string => $case->id->dataSet, $cases));
		$this->assertSame([['/b.php'], ['/a.php']], array_map(static fn (BenchmarkCase $case): array => $case->arguments, $cases));
		$this->assertSame([InMemoryExpectations::class, AttributeExpectations::class], array_map(static fn (BenchmarkCase $case): string => $case->expectations::class, $cases));
		$this->assertSame([['instructions' => 2], null], array_map(static fn (BenchmarkCase $case): ?array => $case->expectations->read()?->toArray(), $cases));
	}

	/**
	 * @return iterable<string, array{class-string, string, string}>
	 */
	public static function dataTolerance(): iterable
	{
		yield 'on the method' => [ToleranceBench::class, 'benchOwn', '±5 % of each metric'];
		yield 'on the class' => [ToleranceBench::class, 'benchOfTheClass', '±20 % of each metric'];
		yield 'a custom one' => [ToleranceBench::class, 'benchCustom', 'no difference'];
		yield 'the default' => [DefaultBench::class, 'benchDefault', '±10 % of each metric'];
	}

	/**
	 * @param class-string $class
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('dataTolerance')]
	public function testTolerance(string $class, string $method, string $description): void
	{
		$this->assertSame($description, self::expandOne($class, $method)->tolerance->describe());
	}

	/**
	 * @return iterable<array{string, string}>
	 */
	public static function dataInvalid(): iterable
	{
		yield ['benchMissingProvider', '::benchMissingProvider(): data provider missing() does not exist.'];
		yield ['benchNonStaticProvider', '::benchNonStaticProvider(): data provider nonStaticProvider() must be public and static.'];
		yield ['benchDuplicateNames', '::benchDuplicateNames(): data provider duplicateNames() yields data set a twice.'];
		yield ['benchNotArrays', '::benchNotArrays(): data set a of notArrays() is neither an array of arguments nor a DataSet.'];
		yield ['benchTwoSources', '::benchTwoSources(): data set a has its own expectations and an #[ExpectedFor] attribute.'];
		yield ['benchDataSetWithoutProvider', '::benchDataSetWithoutProvider(): #[ExpectedFor] names data set a, but the method has no data provider.'];
		yield ['benchStaleDataSet', '::benchStaleDataSet(): #[ExpectedFor] names data set b, which plain() does not yield.'];
		yield ['benchExpectedWithDataProvider', '::benchExpectedWithDataProvider(): #[Expected] is for a method without a data provider, use #[ExpectedFor] for each data set.'];
		yield ['benchInvalidQuantity', '::benchInvalidQuantity(): "1.2X" is not a quantity'];
		yield ['benchBefore', '::benchBefore(): a benchmark method cannot be a #[Before] method, give the hook a name that does not start with bench.'];
		yield ['benchAfter', '::benchAfter(): a benchmark method cannot be a #[After] method, give the hook a name that does not start with bench.'];
		yield ['benchNotIterable', '::benchNotIterable(): data provider notIterable() must return an iterable.'];
		yield ['benchTwoTolerances', '::benchTwoTolerances(): it has more than one tolerance policy: Tally\Attribute\RelativeTolerance, Tally\Attribute\DurationTolerance.'];
		yield ['benchTwoExpected', '::benchTwoExpected(): #[Expected] can be used only once.'];
		yield ['benchTwoExpectedFor', '::benchTwoExpectedFor(): #[ExpectedFor] names data set a twice.'];
		yield ['benchInvalidQuantityFor', '::benchInvalidQuantityFor(): "1.2X" is not a quantity'];
		yield ['benchNegativeWarmup', '::benchNegativeWarmup(): #[Warmup] needs zero or more calls.'];
		yield ['benchWarmupOfWrongType', '::benchWarmupOfWrongType(): #[Warmup] argument calls must be int, string given.'];
		yield ['benchExpectedWithoutMemory', '::benchExpectedWithoutMemory(): #[Expected] needs argument memory.'];
		yield ['benchNegativeTolerance', '::benchNegativeTolerance(): #[RelativeTolerance] needs zero or more, -0.1 given.'];
		yield ['benchInvalidDataProvider', '::benchInvalidDataProvider(): #[DataProvider] argument method must be string, int given.'];
		yield ['benchInvalidExpectedFor', '::benchInvalidExpectedFor(): #[ExpectedFor] needs argument dataSet.'];
		yield ['benchProviderWithParameter', '::benchProviderWithParameter(): data provider withParameter() cannot take arguments, Tally calls it without any.'];
		yield ['benchProviderThrows', '::benchProviderThrows(): data provider throws() threw RuntimeException: Cannot read the fixtures.'];
		yield ['benchProviderThrowsWhileYielding', '::benchProviderThrowsWhileYielding(): data provider throwsWhileYielding() threw Error: Call to undefined function Tally\\Tests\\Fixtures\\Invalid\\missing()'];
		yield ['benchProviderYieldsInvalid', '::benchProviderYieldsInvalid(): data set b of yieldsInvalid() is neither an array of arguments nor a DataSet.'];
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('dataInvalid')]
	public function testInvalid(string $method, string $message): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage(InvalidBench::class . $message);
		self::expand(InvalidBench::class, $method);
	}

	/**
	 * @param class-string $class
	 * @throws InvalidBenchmarkException
	 */
	private static function expandOne(string $class, string $method): BenchmarkCase
	{
		$cases = self::expand($class, $method);
		self::assertCount(1, $cases);

		return $cases[0];
	}

	/**
	 * @param class-string $class
	 * @return list<BenchmarkCase>
	 * @throws InvalidBenchmarkException
	 */
	private static function expand(string $class, string $method): array
	{
		$reflection = new ReflectionClass($class);
		$expander = new CaseExpander(new ExpectedAttributeRewriter(), new AttributeFactory(new ValueTypeCheck()));

		return $expander->expand($reflection, $reflection->getMethod($method), $expander->hooks($reflection));
	}

}
