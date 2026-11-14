<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Runner;

use PHPUnit\Framework\TestCase;
use Tally\Runner\CodSpeedCounter;
use Tally\Tests\RecordingInstrumentHooks;

final class CodSpeedCounterTest extends TestCase
{

	public function testInstrumentsFromPreparingToFinishingAndCollectsOnlyInTheSpans(): void
	{
		$hooks = new RecordingInstrumentHooks();
		$counter = new CodSpeedCounter($hooks);

		$counter->prepare();
		$counter->start();
		$counter->stop('App\FooBench::benchFoo@a#baseline');
		$counter->start();
		$counter->pause();
		$counter->resume();
		$counter->stop('App\FooBench::benchFoo@a');
		$counter->finish();

		$this->assertSame([
			'instrument',
			'toggle',
			'toggle',
			'dump App\FooBench::benchFoo@a#baseline',
			'toggle',
			'toggle',
			'toggle',
			'toggle',
			'dump App\FooBench::benchFoo@a',
			'stop',
		], $hooks->calls);
	}

}
