<?php declare(strict_types = 1);

namespace Tally\Discovery\data\invalid;

use Tally\Attribute\BeforeMethods;
use Tally\Attribute\DataProvider;
use Tally\Attribute\Expected;
use Tally\DataSet;
use Tally\Expectation\InMemoryExpectations;
use Tally\Metrics;

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

	public static function withSource(): iterable
	{
		yield DataSet::named('a', [])->withExpectations(new InMemoryExpectations(Metrics::none()));
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
	#[Expected(dataSet: 'a', instructions: '1')]
	public function benchTwoSources(): void
	{
	}

	#[Expected(dataSet: 'a', instructions: '1')]
	public function benchDataSetWithoutProvider(): void
	{
	}

	#[DataProvider('plain')]
	#[Expected(dataSet: 'b', instructions: '1')]
	public function benchStaleDataSet(): void
	{
	}

	#[DataProvider('plain')]
	#[Expected(instructions: '1')]
	public function benchExpectedWithoutDataSet(): void
	{
	}

	#[Expected(instructions: '1.2X')]
	public function benchInvalidQuantity(): void
	{
	}

	#[BeforeMethods('missing')]
	public function benchMissingBeforeMethod(): void
	{
	}

}
