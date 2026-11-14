<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Refusing;

use Tally\Attribute\DataProvider;
use Tally\DataSet;
use Tally\Expectation\ExpectationException;
use Tally\Expectation\ExpectationSource;
use Tally\Expectation\SourceLocation;
use Tally\Metrics;

/** Expectations a data provider keeps somewhere it cannot rewrite */
final class RefusingExpectations implements ExpectationSource
{

	public function read(): ?Metrics
	{
		return null;
	}

	public function file(): string
	{
		return __FILE__;
	}

	public function edits(string $code, Metrics $measured): array
	{
		throw new ExpectationException('The data set is generated.');
	}

	public function location(): SourceLocation
	{
		return new SourceLocation(__FILE__, 1);
	}

}

final class RefusingBench
{

	/**
	 * @return iterable<DataSet>
	 */
	public static function generated(): iterable
	{
		yield DataSet::named('generated', [])->withExpectations(new RefusingExpectations());
	}

	#[DataProvider('generated')]
	public function benchGenerated(): void
	{
	}

}
