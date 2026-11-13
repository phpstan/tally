<?php declare(strict_types = 1);

namespace Tally;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MetricsTest extends TestCase
{

	public function testToArrayIsInTheOrderOfTheMetricsNotOfInsertion(): void
	{
		$metrics = Metrics::none()
			->with(Metric::Memory, 3)
			->with(Metric::Instructions, 1);

		$this->assertSame(['instructions' => 1, 'memory' => 3], $metrics->toArray());
		$this->assertNull($metrics->get(Metric::Cycles));
	}

	public function testMergeKeepsWhatTheOtherDoesNotHave(): void
	{
		$expected = Metrics::fromArray(['instructions' => 1, 'cycles' => 2, 'memory' => 3]);
		$measuredLocally = Metrics::fromArray(['memory' => 30]);

		$this->assertSame(['instructions' => 1, 'cycles' => 2, 'memory' => 30], $expected->merge($measuredLocally)->toArray());
	}

	public function testFromArrayRejectsUnknownMetrics(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('peakMemory');
		Metrics::fromArray(['peakMemory' => 3]);
	}

	public function testFormat(): void
	{
		$this->assertSame('3.119B', Metric::Instructions->format()->format(3_118_976_850));
		$this->assertSame('44.49MiB', Metric::Memory->format()->format(46_647_360));
	}

}
