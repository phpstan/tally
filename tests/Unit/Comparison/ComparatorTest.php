<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Comparison;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Attribute\RelativeTolerance;
use Tally\CaseId;
use Tally\Comparison\Comparator;
use Tally\Comparison\MetricComparison;
use Tally\Comparison\Percent;
use Tally\Comparison\Status;
use Tally\Expectation\SourceLocation;
use Tally\Measurement;
use Tally\Metrics;
use Tally\Tests\Fixtures\Tolerance\Exact;
use function array_map;

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
