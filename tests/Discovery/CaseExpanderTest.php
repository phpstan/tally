<?php declare(strict_types = 1);

namespace PHPStan\Tally\Discovery;

use PHPStan\Tally\BenchmarkCase;
use PHPStan\Tally\Discovery\data\invalid\InvalidBench;
use PHPStan\Tally\Discovery\data\suite\FilesBench;
use PHPStan\Tally\Discovery\data\suite\Nested\IndexedBench;
use PHPStan\Tally\Discovery\data\suite\Nested\LoopBench;
use PHPStan\Tally\Discovery\data\suite\StringBench;
use PHPStan\Tally\Expectation\AttributeExpectations;
use PHPStan\Tally\Expectation\InMemoryExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use function array_map;

class CaseExpanderTest extends TestCase
{

	public function testMethodWithoutDataProvider(): void
	{
		[$case] = self::expand(StringBench::class, 'benchSprintf');

		$this->assertSame(StringBench::class . '::benchSprintf', $case->id->toString());
		$this->assertSame([], $case->arguments);
		$this->assertSame(0, $case->warmup);
		$this->assertInstanceOf(AttributeExpectations::class, $case->expectations);
		$this->assertSame(['instructions' => 1204, 'cycles' => 1387, 'memory' => 0], $case->expectations->read()?->toArray());
		$this->assertSame(12, $case->expectations->location()->line);
	}

	public function testWarmupAndNoExpectations(): void
	{
		[$case] = self::expand(StringBench::class, 'benchConcat');

		$this->assertSame(2, $case->warmup);
		$this->assertNull($case->expectations->read());
	}

	public function testDataProviderKeepsItsOrder(): void
	{
		$cases = self::expand(LoopBench::class, 'benchSum');

		$this->assertSame(['size-1000', 'size-10', 'size-100'], array_map(static fn (BenchmarkCase $case): ?string => $case->id->dataSet, $cases));
		$this->assertSame([[1000], [10], [100]], array_map(static fn (BenchmarkCase $case): array => $case->arguments, $cases));
		$this->assertSame(
			[null, ['instructions' => 100], ['instructions' => 1000]],
			array_map(static fn (BenchmarkCase $case): ?array => $case->expectations->read()?->toArray(), $cases),
		);
		// the class's before methods run first
		$this->assertSame(['setUp', 'prepare'], $cases[0]->beforeMethods);
		$this->assertSame(['tearDown'], $cases[0]->afterMethods);
	}

	public function testDataSetsOfAList(): void
	{
		$cases = self::expand(IndexedBench::class, 'benchIndexed');

		$this->assertSame(['#0', '#1'], array_map(static fn (BenchmarkCase $case): ?string => $case->id->dataSet, $cases));
		$this->assertSame([[1], [2]], array_map(static fn (BenchmarkCase $case): array => $case->arguments, $cases));
	}

	public function testDataSetWithItsOwnExpectations(): void
	{
		[$b, $a] = self::expand(FilesBench::class, 'benchFile');

		$this->assertSame('b.php', $b->id->dataSet);
		$this->assertInstanceOf(InMemoryExpectations::class, $b->expectations);
		$this->assertSame(['instructions' => 2], $b->expectations->read()?->toArray());
		$this->assertSame(['/a.php'], $a->arguments);
		$this->assertInstanceOf(AttributeExpectations::class, $a->expectations);
		$this->assertNull($a->expectations->read());
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
		yield ['benchTwoSources', '::benchTwoSources(): data set a has its own expectations and an #[Expected] attribute.'];
		yield ['benchDataSetWithoutProvider', '::benchDataSetWithoutProvider(): #[Expected] names data set a, but the method has no data provider.'];
		yield ['benchStaleDataSet', '::benchStaleDataSet(): #[Expected] names data set b, which plain() does not yield.'];
		yield ['benchExpectedWithoutDataSet', '::benchExpectedWithoutDataSet(): #[Expected] needs dataSet, because the method has a data provider.'];
		yield ['benchInvalidQuantity', '::benchInvalidQuantity(): "1.2X" is not a quantity'];
		yield ['benchMissingBeforeMethod', '::benchMissingBeforeMethod(): before method missing() does not exist.'];
	}

	#[DataProvider('dataInvalid')]
	public function testInvalid(string $method, string $message): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage(InvalidBench::class . $message);
		self::expand(InvalidBench::class, $method);
	}

	/**
	 * @param class-string $class
	 * @return list<BenchmarkCase>
	 */
	private static function expand(string $class, string $method): array
	{
		$reflection = new ReflectionClass($class);

		return (new CaseExpander())->expand($reflection, $reflection->getMethod($method));
	}

}
