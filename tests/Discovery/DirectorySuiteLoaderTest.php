<?php declare(strict_types = 1);

namespace Tally\Discovery;

use PHPUnit\Framework\TestCase;
use Tally\BenchmarkCase;
use function array_map;

class DirectorySuiteLoaderTest extends TestCase
{

	public function testLoadsBenchMethodsOfBenchFilesInNaturalOrder(): void
	{
		$namespace = 'Tally\\Discovery\\data\\suite\\';

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
		], self::ids(new DirectorySuiteLoader([__DIR__ . '/data/suite'], new CaseExpander())));
	}

	public function testLoadsAFile(): void
	{
		$this->assertSame(
			['Tally\\Discovery\\data\\suite\\Nested\\IndexedBench::benchIndexed@#0', 'Tally\\Discovery\\data\\suite\\Nested\\IndexedBench::benchIndexed@#1'],
			self::ids(new DirectorySuiteLoader([__DIR__ . '/data/suite/Nested/IndexedBench.php'], new CaseExpander())),
		);
	}

	public function testFailsForAMissingPath(): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage('does-not-exist');
		(new DirectorySuiteLoader([__DIR__ . '/does-not-exist'], new CaseExpander()))->load();
	}

	/**
	 * @return list<string>
	 */
	private static function ids(DirectorySuiteLoader $loader): array
	{
		return array_map(static fn (BenchmarkCase $case): string => $case->id->toString(), $loader->load());
	}

}
