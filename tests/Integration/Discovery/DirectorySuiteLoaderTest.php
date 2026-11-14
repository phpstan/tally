<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Discovery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\BenchmarkCase;
use Tally\Configuration;
use Tally\Discovery\ArgumentResolver;
use Tally\Discovery\AttributeFactory;
use Tally\Discovery\CaseExpander;
use Tally\Discovery\ConstructorArguments;
use Tally\Discovery\DirectorySuiteLoader;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Discovery\ValueTypeCheck;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Report\RelativePath;
use Tally\Tests\Expressions;
use Tally\Tests\Fixtures\Loading\TwoInvalid\ABench;
use Tally\Tests\Fixtures\Loading\TwoInvalid\ZBench;
use function array_map;
use function class_exists;
use function dirname;

final class DirectorySuiteLoaderTest extends TestCase
{

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testLoadsBenchMethodsOfBenchFilesInNaturalOrder(): void
	{
		$namespace = 'Tally\\Tests\\Fixtures\\Suite\\';

		$this->assertSame([
			$namespace . 'FilesBench::benchFile@a.php',
			$namespace . 'FilesBench::benchFile@b.php',
			$namespace . 'Nested\\IndexedBench::benchIndexed@#0',
			$namespace . 'Nested\\IndexedBench::benchIndexed@#1',
			$namespace . 'Nested\\LoopBench::benchSum@size-10',
			$namespace . 'Nested\\LoopBench::benchSum@size-100',
			$namespace . 'Nested\\LoopBench::benchSum@size-1000',
			$namespace . 'StringBench::benchConcat',
			$namespace . 'StringBench::benchSprintf',
		], self::ids([__DIR__ . '/../../Fixtures/Suite']));
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testLoadsAFile(): void
	{
		$this->assertSame(
			['Tally\\Tests\\Fixtures\\Suite\\Nested\\IndexedBench::benchIndexed@#0', 'Tally\\Tests\\Fixtures\\Suite\\Nested\\IndexedBench::benchIndexed@#1'],
			self::ids([__DIR__ . '/../../Fixtures/Suite/Nested/IndexedBench.php']),
		);
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testLoadsFilesAndDirectories(): void
	{
		$this->assertSame(
			[
				'Tally\\Tests\\Fixtures\\Suite\\Nested\\IndexedBench::benchIndexed@#0',
				'Tally\\Tests\\Fixtures\\Suite\\Nested\\IndexedBench::benchIndexed@#1',
				'Tally\\Tests\\Fixtures\\Suite\\Nested\\LoopBench::benchSum@size-10',
				'Tally\\Tests\\Fixtures\\Suite\\Nested\\LoopBench::benchSum@size-100',
				'Tally\\Tests\\Fixtures\\Suite\\Nested\\LoopBench::benchSum@size-1000',
				'Tally\\Tests\\Fixtures\\Suite\\StringBench::benchConcat',
				'Tally\\Tests\\Fixtures\\Suite\\StringBench::benchSprintf',
			],
			self::ids([__DIR__ . '/../../Fixtures/Suite/StringBench.php', __DIR__ . '/../../Fixtures/Suite/Nested']),
		);
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testFailsForAMissingPath(): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage('does-not-exist');
		self::ids([__DIR__ . '/does-not-exist']);
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function dataInvalid(): iterable
	{
		yield 'a syntax error' => ['Syntax', 'tests/Fixtures/Loading/Syntax/BrokenBench.php: syntax error, unexpected token ";", expecting ")" on line 10.'];
		yield 'an error while loading' => ['Throwing', 'tests/Fixtures/Loading/Throwing/ThrowingBench.php: loading it threw Error: Class "Tally\\Tests\\Fixtures\\Loading\\Throwing\\MissingParent" not found'];
		yield 'a private bench* method' => ['Private', 'Tally\\Tests\\Fixtures\\Loading\\Private\\PrivateBench::benchHelper(): a bench* method must be public and not static. Rename it if it is not a benchmark.'];
		yield 'a constructor that takes data' => ['Constructor', 'Tally\\Tests\\Fixtures\\Loading\\Constructor\\SizedBench::__construct(): parameter $size is neither a Meter nor a ScratchDirectory.'];
		yield 'a static bench* method' => ['Static', 'Tally\\Tests\\Fixtures\\Loading\\Static\\StaticBench::benchmarks(): a bench* method must be public and not static. Rename it if it is not a benchmark.'];
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('dataInvalid')]
	public function testInvalid(string $directory, string $message): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage($message);
		self::ids([__DIR__ . '/../../Fixtures/Loading/' . $directory]);
	}

	/**
	 * The class autoloaded first does not decide which error is reported.
	 *
	 * @throws InvalidBenchmarkException
	 */
	public function testReportsTheFirstInvalidClassByName(): void
	{
		self::assertTrue(class_exists(ZBench::class));

		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage(ABench::class . '::__construct()');
		self::ids([__DIR__ . '/../../Fixtures/Loading/TwoInvalid']);
	}

	/**
	 * Classes in other files and classes without bench* methods are not benchmarks.
	 *
	 * @throws InvalidBenchmarkException
	 */
	public function testNoBenchmarks(): void
	{
		$this->assertSame([], self::ids([__DIR__ . '/../../Fixtures/Loading/NoBenchmarks']));
	}

	/**
	 * @param list<string> $paths
	 * @return list<string>
	 * @throws InvalidBenchmarkException
	 */
	private static function ids(array $paths): array
	{
		$loader = new DirectorySuiteLoader(new CaseExpander(new ExpectedAttributeRewriter(), new AttributeFactory(new ValueTypeCheck()), new ArgumentResolver(), (new Expressions())->growthPoints()), new ConstructorArguments(), new ExpectedAttributeRewriter(), new RelativePath());

		return array_map(static fn (BenchmarkCase $case): string => $case->id->toString(), $loader->load($paths, dirname(__DIR__, 3), Configuration::create()));
	}

}
