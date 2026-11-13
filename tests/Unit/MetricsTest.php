<?php declare(strict_types = 1);

namespace Tally\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Metric;
use Tally\Metrics;

final class MetricsTest extends TestCase
{

	public function testToArrayIsInTheOrderOfTheMetricsNotOfInsertion(): void
	{
		$metrics = Metrics::none()
			->with(Metric::Memory, 3)
			->with(Metric::Instructions, 1);

		$this->assertSame(['instructions' => 1, 'memory' => 3], $metrics->toArray());
		$this->assertNull($metrics->get(Metric::Cycles));
	}

	public function testFromArrayRejectsUnknownMetrics(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('peakMemory');
		Metrics::fromArray(['peakMemory' => 3]);
	}

	/**
	 * @return iterable<string, array{Metrics, Metrics, array<string, int>}>
	 */
	public static function minusProvider(): iterable
	{
		yield 'subtracts each metric' => [
			Metrics::fromArray(['instructions' => 1000, 'cycles' => 1500, 'memory' => 64]),
			Metrics::fromArray(['instructions' => 300, 'cycles' => 400, 'memory' => 16]),
			['instructions' => 700, 'cycles' => 1100, 'memory' => 48],
		];
		yield 'a metric the baseline does not have stays' => [
			Metrics::fromArray(['instructions' => 1000, 'memory' => 64]),
			Metrics::fromArray(['instructions' => 300]),
			['instructions' => 700, 'memory' => 64],
		];
		yield 'a metric only the baseline has is not added' => [
			Metrics::fromArray(['instructions' => 1000]),
			Metrics::fromArray(['instructions' => 300, 'cycles' => 400]),
			['instructions' => 700],
		];
		yield 'never below zero' => [
			Metrics::fromArray(['instructions' => 299, 'cycles' => 400]),
			Metrics::fromArray(['instructions' => 300, 'cycles' => 400]),
			['instructions' => 0, 'cycles' => 0],
		];
	}

	/**
	 * @param array<string, int> $expected
	 */
	#[DataProvider('minusProvider')]
	public function testMinus(Metrics $metrics, Metrics $baseline, array $expected): void
	{
		$this->assertSame($expected, $metrics->minus($baseline)->toArray());
	}

	public function testMergeTakesTheOtherValuesFirst(): void
	{
		$expected = Metrics::fromArray(['instructions' => 1000, 'cycles' => 1500, 'memory' => 64]);
		$measured = Metrics::fromArray(['memory' => 80]);

		$this->assertSame(['instructions' => 1000, 'cycles' => 1500, 'memory' => 80], $expected->merge($measured)->toArray());
		$this->assertSame(64, $expected->get(Metric::Memory));
	}

	public function testFormat(): void
	{
		$this->assertSame('3.119B', Metric::Instructions->format()->format(3_118_976_850));
		$this->assertSame('44.49MiB', Metric::Memory->format()->format(46_647_360));
	}

}
