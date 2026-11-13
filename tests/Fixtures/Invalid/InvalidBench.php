<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Invalid;

use Tally\Attribute\BeforeMethods;
use Tally\Attribute\DataProvider;
use Tally\Attribute\DurationTolerance;
use Tally\Attribute\Expected;
use Tally\Attribute\ExpectedFor;
use Tally\Attribute\RelativeTolerance;
use Tally\Attribute\Warmup;
use Tally\DataSet;
use Tally\Metrics;
use Tally\Tests\InMemoryExpectations;

final class InvalidBench
{

	public function nonStaticProvider(): iterable
	{
		yield [1];
	}

	public static function duplicateNames(): iterable
	{
		yield DataSet::named('a', []);
		yield DataSet::named('a', []);
	}

	public static function notArrays(): iterable
	{
		yield 'a' => 1;
	}

	public static function plain(): iterable
	{
		yield 'a' => [];
	}

	public static function notIterable(): int
	{
		return 1;
	}

	private function hidden(): void
	{
	}

	public static function withSource(): iterable
	{
		yield DataSet::named('a', [])->withExpectations(new InMemoryExpectations(Metrics::none(), '/benchmarks/InMemoryBench.php'));
	}

	#[DataProvider('missing')]
	public function benchMissingProvider(): void
	{
	}

	#[DataProvider('nonStaticProvider')]
	public function benchNonStaticProvider(): void
	{
	}

	#[DataProvider('duplicateNames')]
	public function benchDuplicateNames(): void
	{
	}

	#[DataProvider('notArrays')]
	public function benchNotArrays(): void
	{
	}

	#[DataProvider('withSource')]
	#[ExpectedFor('a', instructions: '1', cycles: '1', memory: '0B')]
	public function benchTwoSources(): void
	{
	}

	#[ExpectedFor('a', instructions: '1', cycles: '1', memory: '0B')]
	public function benchDataSetWithoutProvider(): void
	{
	}

	#[DataProvider('plain')]
	#[ExpectedFor('b', instructions: '1', cycles: '1', memory: '0B')]
	public function benchStaleDataSet(): void
	{
	}

	#[DataProvider('plain')]
	#[Expected(instructions: '1', cycles: '1', memory: '0B')]
	public function benchExpectedWithDataProvider(): void
	{
	}

	#[Expected(instructions: '1.2X', cycles: '1', memory: '0B')]
	public function benchInvalidQuantity(): void
	{
	}

	#[BeforeMethods('missing')]
	public function benchMissingBeforeMethod(): void
	{
	}

	#[DataProvider('notIterable')]
	public function benchNotIterable(): void
	{
	}

	#[DataProvider('plain')]
	#[ExpectedFor('a', instructions: '1', cycles: '1', memory: '0B')]
	#[ExpectedFor('a', instructions: '2', cycles: '2', memory: '0B')]
	public function benchTwoExpectedFor(): void
	{
	}

	#[DataProvider('plain')]
	#[ExpectedFor('a', instructions: '1.2X', cycles: '1', memory: '0B')]
	public function benchInvalidQuantityFor(): void
	{
	}

	#[Expected(instructions: '1', cycles: '1', memory: '0B')]
	#[Expected(instructions: '2', cycles: '2', memory: '0B')]
	public function benchTwoExpected(): void
	{
	}

	#[RelativeTolerance(0.1)]
	#[DurationTolerance([0 => 0.1], 1, 1, 0.1)]
	public function benchTwoTolerances(): void
	{
	}

	#[Warmup(-1)]
	public function benchNegativeWarmup(): void
	{
	}

	#[BeforeMethods('hidden')]
	public function benchPrivateBeforeMethod(): void
	{
		$this->hidden();
	}

}
