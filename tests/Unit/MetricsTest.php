<?php declare(strict_types = 1);

namespace Tally\Tests\Unit;

use InvalidArgumentException;
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

	public function testFormat(): void
	{
		$this->assertSame('3.119B', Metric::Instructions->format()->format(3_118_976_850));
		$this->assertSame('44.49MiB', Metric::Memory->format()->format(46_647_360));
	}

}
