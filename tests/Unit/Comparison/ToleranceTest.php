<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Comparison;

use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Attribute\DurationTolerance;
use Tally\Attribute\MetricTolerance;
use Tally\Attribute\RelativeTolerance;
use Tally\Metric;

final class ToleranceTest extends TestCase
{

	public function testRelativeToleranceAllowsTheSameShareOfEveryMetric(): void
	{
		$tolerance = new RelativeTolerance(0.05);

		foreach (Metric::cases() as $metric) {
			$this->assertSame(0.05, $tolerance->allowedChange($metric, 1_000_000), $metric->value);
		}
		$this->assertSame('±5 % of each metric', $tolerance->describe());
		$this->assertSame('±0 % of each metric', (new RelativeTolerance(0))->describe());
	}

	/**
	 * @return iterable<string, array{Metric, int, float}>
	 */
	public static function metricProvider(): iterable
	{
		yield 'instructions, whatever their number' => [Metric::Instructions, 1_000, 0.02];
		yield 'memory, whatever its size' => [Metric::Memory, 1_000, 0.05];
		yield 'cycles where the share allows more than the floor' => [Metric::Cycles, 100_000, 0.05];
		yield 'cycles where the share and the floor allow the same' => [Metric::Cycles, 40_000, 0.05];
		yield 'cycles where the floor allows more than the share' => [Metric::Cycles, 10_000, 0.2];
		yield 'cycles of an expected 0, where any cycles fail anyway' => [Metric::Cycles, 0, 2_000.0];
	}

	#[DataProvider('metricProvider')]
	public function testMetricToleranceAllowsEachMetricItsShareAndTheCyclesAFloor(Metric $metric, int $expected, float $allowed): void
	{
		$tolerance = new MetricTolerance(instructions: 0.02, cycles: 0.05, memory: 0.05, cyclesAtLeast: 2_000);

		$this->assertEqualsWithDelta($allowed, $tolerance->allowedChange($metric, $expected), 1e-9);
		$this->assertSame('±2 % instructions, ±5 % cycles but at least ±2,000, ±5 % memory', $tolerance->describe());
		$this->assertSame('±0 % instructions, ±0 % cycles but at least ±0, ±0 % memory', (new MetricTolerance(0, 0, 0, 0))->describe());
	}

	/**
	 * @return iterable<string, array{int, float}>
	 */
	public static function durationProvider(): iterable
	{
		yield 'quickest range, allowed 50 % of its 100 ms' => [10, 50 / 10];
		yield 'just below 100 ms' => [99, 50 / 99];
		yield '25 % would be less than the 50 ms allowed below 100 ms' => [150, 50 / 150];
		yield 'at 200 ms, 25 % is exactly the 50 ms' => [200, 0.25];
		yield '25 %' => [300, 0.25];
		yield '20 % would be less than the 125 ms allowed at 500 ms' => [600, 125 / 600];
		yield '20 %' => [1000, 0.2];
		yield '10 % would be less than the 400 ms allowed at 2 s' => [2500, 400 / 2500];
		yield '10 %' => [5000, 0.1];
	}

	#[DataProvider('durationProvider')]
	public function testDurationToleranceByExpectedMilliseconds(int $milliseconds, float $allowed): void
	{
		$tolerance = self::phpstan();

		$this->assertEqualsWithDelta($allowed, $tolerance->allowedChange(Metric::Instructions, $milliseconds * 5_800_000), 1e-9);
		$this->assertEqualsWithDelta($allowed, $tolerance->allowedChange(Metric::Cycles, $milliseconds * 7_400_000), 1e-9);
		$this->assertSame(0.1, $tolerance->allowedChange(Metric::Memory, $milliseconds));
	}

	public function testDurationToleranceWithItsOwnTable(): void
	{
		$tolerance = new DurationTolerance([0 => 0.4, 10 => 0.1], 1000, 2000, 0.05);

		// 5 ms: 40 % of 10 ms
		$this->assertEqualsWithDelta(0.8, $tolerance->allowedChange(Metric::Instructions, 5_000), 1e-9);
		// 30 ms: 10 % would be 3 ms, less than the 4 ms allowed at 10 ms
		$this->assertEqualsWithDelta(4 / 30, $tolerance->allowedChange(Metric::Cycles, 60_000), 1e-9);
		$this->assertEqualsWithDelta(0.1, $tolerance->allowedChange(Metric::Instructions, 100_000), 1e-9);
		$this->assertSame(0.05, $tolerance->allowedChange(Metric::Memory, 1));
		$this->assertSame(
			'instructions and cycles by the time they take at 1.000K instructions or 2.000K cycles per ms: ±4 ms below 10 ms, ±10 % from 10 ms, each at least what the range below allows; ±5 % memory',
			$tolerance->describe(),
		);
	}

	public function testDurationToleranceWithOneRange(): void
	{
		$tolerance = new DurationTolerance([0 => 0.2], 5_800_000, 7_400_000, 0.1);

		$this->assertSame(0.2, $tolerance->allowedChange(Metric::Instructions, 1));
		$this->assertSame(
			'instructions and cycles by the time they take at 5.800M instructions or 7.400M cycles per ms: ±20 % from 0 ms, each at least what the range below allows; ±10 % memory',
			$tolerance->describe(),
		);
	}

	public function testDurationToleranceAtOneInstructionAndCyclePerMillisecond(): void
	{
		$tolerance = new DurationTolerance([0 => 0.5, 100 => 0.1], 1, 1, 0.1);

		// 1000 ms, where 10 % is more than the 50 ms allowed below 100 ms; 100 ms, where 10 % is less
		$this->assertEqualsWithDelta(0.1, $tolerance->allowedChange(Metric::Instructions, 1000), 1e-9);
		$this->assertEqualsWithDelta(0.5, $tolerance->allowedChange(Metric::Cycles, 100), 1e-9);
	}

	public function testDurationToleranceUsesOnlyTheRangesTheDurationReaches(): void
	{
		// a slower range allowing a larger share, so that counting a range it does not reach would show
		$tolerance = new DurationTolerance([0 => 0.1, 100 => 0.5], 1, 1, 0.1);

		// 50 ms: 10 % of 100 ms
		$this->assertEqualsWithDelta(0.2, $tolerance->allowedChange(Metric::Instructions, 50), 1e-9);
		// exactly 100 ms is in the second range
		$this->assertEqualsWithDelta(0.5, $tolerance->allowedChange(Metric::Instructions, 100), 1e-9);
	}

	public function testDurationToleranceRoundsTheMillisecondsOfTheQuickestRange(): void
	{
		$this->assertSame(
			'instructions and cycles by the time they take at 1 instructions or 1 cycles per ms: ±3 ms below 10 ms, ±10 % from 10 ms, each at least what the range below allows; ±10 % memory',
			(new DurationTolerance([0 => 0.25, 10 => 0.1], 1, 1, 0.1))->describe(),
		);
		$this->assertSame(
			'instructions and cycles by the time they take at 1 instructions or 1 cycles per ms: ±2 ms below 10 ms, ±10 % from 10 ms, each at least what the range below allows; ±10 % memory',
			(new DurationTolerance([0 => 0.24, 10 => 0.1], 1, 1, 0.1))->describe(),
		);
	}

	public function testDurationToleranceDescribesThePHPStansTable(): void
	{
		$this->assertSame(
			'instructions and cycles by the time they take at 5.800M instructions or 7.400M cycles per ms: ±50 ms below 100 ms, ±25 % from 100 ms, ±20 % from 500 ms, ±10 % from 2000 ms, each at least what the range below allows; ±10 % memory',
			self::phpstan()->describe(),
		);
	}

	private static function phpstan(): DurationTolerance
	{
		return new DurationTolerance([0 => 0.5, 100 => 0.25, 500 => 0.2, 2000 => 0.1], 5_800_000, 7_400_000, 0.1);
	}

	/**
	 * @return iterable<string, array{Closure(): object, string}>
	 */
	public static function invalidProvider(): iterable
	{
		yield 'negative relative tolerance' => [static fn () => new RelativeTolerance(-0.1), '#[RelativeTolerance] needs zero or more, -0.1 given.'];
		yield 'negative instructions' => [static fn () => new MetricTolerance(-0.01, 0, 0, 0), '#[MetricTolerance] needs zero or more of each metric.'];
		yield 'negative cycles' => [static fn () => new MetricTolerance(0, -0.01, 0, 0), '#[MetricTolerance] needs zero or more of each metric.'];
		yield 'negative memory' => [static fn () => new MetricTolerance(0, 0, -0.01, 0), '#[MetricTolerance] needs zero or more of each metric.'];
		yield 'negative cycles at least' => [static fn () => new MetricTolerance(0, 0, 0, -1), '#[MetricTolerance] needs zero or more of each metric.'];
		yield 'no tolerances' => [static fn () => new DurationTolerance([], 1, 1, 0.1), '#[DurationTolerance] needs a tolerance from 0 ms.'];
		yield 'not from 0 ms' => [static fn () => new DurationTolerance([1 => 0.1], 1, 1, 0.1), '#[DurationTolerance] needs a tolerance from 0 ms.'];
		yield 'descending' => [static fn () => new DurationTolerance([0 => 0.5, 500 => 0.2, 100 => 0.25], 1, 1, 0.1), '#[DurationTolerance] needs the tolerances in ascending order of milliseconds.'];
		yield 'descending at the end' => [static fn () => new DurationTolerance([0 => 0.5, 1 => 0.2, 2_000 => 0.05, 1_999 => 0.0], 1, 1, 0.1), '#[DurationTolerance] needs the tolerances in ascending order of milliseconds.'];
		yield 'no instructions per millisecond' => [static fn () => new DurationTolerance([0 => 0.5], 0, 1, 0.1), '#[DurationTolerance] needs at least 1 instruction and cycle per millisecond.'];
		yield 'no cycles per millisecond' => [static fn () => new DurationTolerance([0 => 0.5], 1, 0, 0.1), '#[DurationTolerance] needs at least 1 instruction and cycle per millisecond.'];
	}

	/**
	 * @param Closure(): object $create
	 */
	#[DataProvider('invalidProvider')]
	public function testInvalid(Closure $create, string $message): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);

		$create();
	}

}
