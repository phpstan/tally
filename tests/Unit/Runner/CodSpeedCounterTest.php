<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Runner;

use PHPUnit\Framework\TestCase;
use Tally\Runner\CodSpeedCounter;
use Tally\Tests\RecordingInstrumentHooks;

final class CodSpeedCounterTest extends TestCase
{

	public function testPausingTogglesCollectionAndStoppingNamesThePart(): void
	{
		$hooks = new RecordingInstrumentHooks();
		$counter = new CodSpeedCounter($hooks);

		$counter->start();
		$counter->pause();
		$counter->resume();
		$counter->stop('App\FooBench::benchFoo@a');

		$this->assertSame(['start', 'toggle', 'toggle', 'stop', 'executed App\FooBench::benchFoo@a'], $hooks->calls);
	}

}
