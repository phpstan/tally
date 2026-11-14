<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Comparison;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Attribute\RelativeTolerance;
use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Comparison\Comparator;
use Tally\Comparison\MetricComparison;
use Tally\Comparison\Percent;
use Tally\Comparison\Status;
use Tally\Comparison\TolerancePolicy;
use Tally\Expectation\ExpectationException;
use Tally\Expectation\SourceLocation;
use Tally\Measurement;
use Tally\Metrics;
use Tally\Tests\Fixtures\Tolerance\Exact;
use Tally\Tests\InMemoryExpectations;
use Tally\Tests\LinearGrowth;
use function array_map;
use function sprintf;

final class ComparatorTest extends TestCase
{

	/**
	 * @return iterable<string, array{array<string, int>|null, Measurement|null, Status, list<string>, string|null}>
	 */
	public static function compareProvider(): iterable
	{
		$id = CaseId::fromString('A::bench');
		$expected = ['instructions' => 1000, 'cycles' => 2000, 'memory' => 100];

		yield 'within the tolerance on both sides, and exactly at it' => [
			$expected,
			Measurement::measured($id, Metrics::fromArray(['instructions' => 1100, 'cycles' => 1800, 'memory' => 100])),
			Status::Ok,
			['instructions ok +10.0 % ±10 %', 'cycles ok -10.0 % ±10 %', 'memory ok 0.0 % ±10 %'],
			null,
		];
		yield 'slower wins over faster' => [
			$expected,
			Measurement::measured($id, Metrics::fromArray(['instructions' => 1101, 'cycles' => 1799, 'memory' => 100])),
			Status::Slower,
			['instructions slower +10.1 % ±10 %', 'cycles faster -10.1 % ±10 %', 'memory ok 0.0 % ±10 %'],
			null,
		];
		yield 'faster' => [
			$expected,
			Measurement::measured($id, Metrics::fromArray(['instructions' => 500, 'cycles' => 2000, 'memory' => 100])),
			Status::Faster,
			['instructions faster -50.0 % ±10 %', 'cycles ok 0.0 % ±10 %', 'memory ok 0.0 % ±10 %'],
			null,
		];
		yield 'a metric that was not counted is left out' => [
			$expected,
			Measurement::measured($id, Metrics::fromArray(['memory' => 200])),
			Status::Slower,
			['memory slower +100.0 % ±10 %'],
			null,
		];
		yield 'a new case' => [
			null,
			Measurement::measured($id, Metrics::fromArray(['instructions' => 1, 'cycles' => 2, 'memory' => 3])),
			Status::New,
			['instructions new 1 -', 'cycles new 2 -', 'memory new 3 -'],
			null,
		];
		yield 'a failed case keeps its expectations' => [
			$expected,
			Measurement::failed($id, 'It broke.'),
			Status::NotMeasured,
			['instructions not measured 1000 -', 'cycles not measured 2000 -', 'memory not measured 100 -'],
			'It broke.',
		];
		yield 'a case that did not run' => [
			$expected,
			null,
			Status::NotMeasured,
			['instructions not measured 1000 -', 'cycles not measured 2000 -', 'memory not measured 100 -'],
			'It did not run.',
		];
		yield 'a new case that failed' => [
			null,
			Measurement::failed($id, 'It broke.'),
			Status::NotMeasured,
			[],
			'It broke.',
		];
		yield 'nothing expected becomes something' => [
			['instructions' => 10, 'cycles' => 10, 'memory' => 0],
			Measurement::measured($id, Metrics::fromArray(['instructions' => 10, 'cycles' => 10, 'memory' => 8])),
			Status::Slower,
			['instructions ok 0.0 % ±10 %', 'cycles ok 0.0 % ±10 %', 'memory slower - ±10 %'],
			null,
		];
		yield 'nothing expected stays nothing' => [
			['instructions' => 10, 'cycles' => 10, 'memory' => 0],
			Measurement::measured($id, Metrics::fromArray(['instructions' => 10, 'cycles' => 10, 'memory' => 0])),
			Status::Ok,
			['instructions ok 0.0 % ±10 %', 'cycles ok 0.0 % ±10 %', 'memory ok - ±10 %'],
			null,
		];
	}

	/**
	 * @param array<string, int>|null $expected
	 * @param list<string> $metrics metric, status, and change and allowed change, or the value without a change
	 */
	#[DataProvider('compareProvider')]
	public function testCompare(?array $expected, ?Measurement $measurement, Status $status, array $metrics, ?string $failure): void
	{
		$location = new SourceLocation('/bench/ABench.php', 12);

		$comparison = (new Comparator())->compare(
			CaseId::fromString('A::bench'),
			$location,
			$expected === null ? null : Metrics::fromArray($expected),
			new RelativeTolerance(0.1),
			$measurement,
		);

		$this->assertSame('A::bench', $comparison->id->toString());
		$this->assertSame($location, $comparison->location);
		$this->assertSame($status, $comparison->status);
		$this->assertSame($failure, $comparison->failure);
		$this->assertSame('±10 % of each metric', $comparison->tolerance->describe());
		$this->assertSame($metrics, array_map(static fn (MetricComparison $metric): string => self::describe($metric), $comparison->metrics));
		$this->assertTrue($comparison->acceptable);
	}

	/**
	 * @return iterable<string, array{string, TolerancePolicy, Measurement|null, Measurement|null, Status, list<string>, string|null}>
	 */
	public static function dataCompareGrowth(): iterable
	{
		$id = CaseId::fromString('A::bench@large');
		$base = Measurement::measured(CaseId::fromString('A::bench@small'), Metrics::fromArray(['instructions' => 1000, 'cycles' => 5000, 'memory' => 10]));
		$measured = static fn (int $instructions): Measurement => Measurement::measured($id, Metrics::fromArray(['instructions' => $instructions, 'cycles' => 1, 'memory' => 1]));
		$tolerance = new RelativeTolerance(0.1);

		yield 'within the growth and the tolerance' => ['A::bench@large', $tolerance, $measured(11000), $base, Status::Ok, ['instructions ok 10000 11000 ±10 %'], null];
		yield 'above the growth and the tolerance' => ['A::bench@large', $tolerance, $measured(11001), $base, Status::Slower, ['instructions slower 10000 11001 ±10 %'], null];
		yield 'growing less is not reported, the class is an upper bound' => ['A::bench@large', $tolerance, $measured(10), $base, Status::Ok, ['instructions ok 10000 10 ±10 %'], null];
		yield 'exactly the growth without a tolerance' => ['A::bench@large', new Exact(), $measured(10000), $base, Status::Ok, ['instructions ok 10000 10000 ±0 %'], null];
		yield 'above the growth without a tolerance' => ['A::bench@large', new Exact(), $measured(10001), $base, Status::Slower, ['instructions slower 10000 10001 ±0 %'], null];
		yield 'the base is not compared' => ['A::bench@small', $tolerance, $measured(1000), null, Status::Ok, ['instructions ok - 1000 -'], null];
		yield 'the base did not run' => ['A::bench@large', $tolerance, $measured(10000), null, Status::NotMeasured, [], 'Its growth is compared with A::bench@small, which was not measured.'];
		yield 'the base failed' => ['A::bench@large', $tolerance, $measured(10000), Measurement::failed(CaseId::fromString('A::bench@small'), 'Fatal error'), Status::NotMeasured, [], 'Its growth is compared with A::bench@small, which was not measured.'];
		yield 'the case failed' => ['A::bench@large', $tolerance, Measurement::failed($id, 'Fatal error'), $base, Status::NotMeasured, [], 'Fatal error'];
		yield 'the case did not run' => ['A::bench@large', $tolerance, null, $base, Status::NotMeasured, [], 'It did not run.'];
		yield 'the case counted no instructions' => ['A::bench@large', $tolerance, Measurement::measured($id, Metrics::none()), $base, Status::NotMeasured, [], 'It did not count instructions.'];
	}

	/**
	 * @param list<string> $metrics metric, status, expected, measured, and allowed change
	 */
	#[DataProvider('dataCompareGrowth')]
	public function testCompareGrowth(string $id, TolerancePolicy $tolerance, ?Measurement $measurement, ?Measurement $base, Status $status, array $metrics, ?string $failure): void
	{
		$location = new SourceLocation('/bench/ABench.php', 12);

		$comparison = (new Comparator())->compareGrowth(
			CaseId::fromString($id),
			$location,
			LinearGrowth::point(CaseId::fromString('A::bench@small'), 10.0, 10.0),
			$tolerance,
			$measurement,
			$base,
		);

		$this->assertSame($id, $comparison->id->toString());
		$this->assertSame($location, $comparison->location);
		$this->assertSame($status, $comparison->status);
		$this->assertSame($failure, $comparison->failure);
		$this->assertSame('instructions grow at most as O(n) from the smallest data set, ' . $tolerance->describe(), $comparison->tolerance->describe());
		$this->assertSame($metrics, array_map(static fn (MetricComparison $metric): string => sprintf(
			'%s %s %s %s %s',
			$metric->metric->value,
			$metric->status->value,
			$metric->expected ?? '-',
			$metric->measured ?? '-',
			$metric->allowedChange === null ? '-' : '±' . (new Percent($metric->allowedChange))->toString(),
		), $comparison->metrics));
		$this->assertFalse($comparison->acceptable);
	}

	public function testGrowthRoundsTheExpectedInstructions(): void
	{
		$comparison = (new Comparator())->compareGrowth(
			CaseId::fromString('A::bench@large'),
			new SourceLocation('/bench/ABench.php', 12),
			LinearGrowth::point(CaseId::fromString('A::bench@small'), 1.25, 1.25),
			new Exact(),
			Measurement::measured(CaseId::fromString('A::bench@large'), Metrics::fromArray(['instructions' => 4])),
			Measurement::measured(CaseId::fromString('A::bench@small'), Metrics::fromArray(['instructions' => 3])),
		);

		$this->assertSame(Status::Ok, $comparison->status);
		$this->assertSame(4, $comparison->metrics[0]->expected ?? null);
	}

	/**
	 * @throws ExpectationException
	 */
	public function testCompareCaseComparesByExpectationsOrByGrowth(): void
	{
		$expectations = new InMemoryExpectations(Metrics::fromArray(['instructions' => 100]), '/bench/ABench.php');
		$small = new BenchmarkCase(CaseId::fromString('A::bench@small'), [], 0, [], [], $expectations, new RelativeTolerance(0.1), LinearGrowth::point(CaseId::fromString('A::bench@small'), 1.0, 1.0));
		$large = $small->withGrowth(LinearGrowth::point(CaseId::fromString('A::bench@small'), 3.0, 3.0));
		$large = new BenchmarkCase(CaseId::fromString('A::bench@large'), [], 0, [], [], $expectations, new RelativeTolerance(0.1), $large->growth);
		$plain = new BenchmarkCase(CaseId::fromString('A::plain'), [], 0, [], [], $expectations, new RelativeTolerance(0.1), null);
		$measurements = [
			'A::bench@small' => Measurement::measured(CaseId::fromString('A::bench@small'), Metrics::fromArray(['instructions' => 100])),
			'A::bench@large' => Measurement::measured(CaseId::fromString('A::bench@large'), Metrics::fromArray(['instructions' => 400])),
			'A::plain' => Measurement::measured(CaseId::fromString('A::plain'), Metrics::fromArray(['instructions' => 150])),
		];
		$comparator = new Comparator();

		$growing = $comparator->compareCase($large, $measurements);
		$this->assertSame(Status::Slower, $growing->status);
		$this->assertSame(300, $growing->metrics[0]->expected ?? null);
		$this->assertSame('/bench/ABench.php', $growing->location->file);
		$this->assertSame(Status::Ok, $comparator->compareCase($small, $measurements)->status);
		$compared = $comparator->compareCase($plain, $measurements);
		$this->assertSame(Status::Slower, $compared->status);
		$this->assertSame(100, $compared->metrics[0]->expected ?? null);
		$this->assertSame(Status::NotMeasured, $comparator->compareCase($plain, [])->status);
	}

	public function testTheToleranceDependsOnTheExpectedValue(): void
	{
		$comparison = (new Comparator())->compare(
			CaseId::fromString('A::bench'),
			new SourceLocation('/bench/ABench.php', 12),
			Metrics::fromArray(['instructions' => 1000]),
			new Exact(),
			Measurement::measured(CaseId::fromString('A::bench'), Metrics::fromArray(['instructions' => 1001])),
		);

		$this->assertSame(Status::Slower, $comparison->status);
		$this->assertSame(['instructions slower +0.1 % ±0 %'], array_map(static fn (MetricComparison $metric): string => self::describe($metric), $comparison->metrics));
	}

	private static function describe(MetricComparison $metric): string
	{
		$change = $metric->change();
		$allowed = $metric->allowedChange;
		if ($change === null && $allowed === null) {
			return $metric->metric->value . ' ' . $metric->status->value . ' ' . ($metric->measured ?? $metric->expected) . ' -';
		}

		return $metric->metric->value . ' ' . $metric->status->value . ' '
			. ($change === null ? '-' : (new Percent($change))->signed()) . ' '
			. ($allowed === null ? '-' : '±' . (new Percent($allowed))->toString());
	}

}
