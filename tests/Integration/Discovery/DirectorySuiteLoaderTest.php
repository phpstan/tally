<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Discovery;

use PHPUnit\Framework\TestCase;
use Tally\BenchmarkCase;
use Tally\Discovery\CaseExpander;
use Tally\Discovery\DirectorySuiteLoader;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Expectation\ExpectedAttributeRewriter;
use function array_map;

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
	 * @param list<string> $paths
	 * @return list<string>
	 * @throws InvalidBenchmarkException
	 */
	private static function ids(array $paths): array
	{
		$loader = new DirectorySuiteLoader(new CaseExpander(new ExpectedAttributeRewriter()));

		return array_map(static fn (BenchmarkCase $case): string => $case->id->toString(), $loader->load($paths));
	}

}
