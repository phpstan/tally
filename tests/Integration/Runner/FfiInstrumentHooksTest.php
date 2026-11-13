<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Runner;

use PHPUnit\Framework\TestCase;
use Tally\Runner\CodSpeedException;
use Tally\Runner\FfiInstrumentHooks;
use Tally\Tests\InstrumentHooksFixture;
use function getmypid;
use function implode;
use function sprintf;

final class FfiInstrumentHooksTest extends TestCase
{

	/**
	 * @throws CodSpeedException
	 */
	public function testCallsTheLibraryWithTheInstanceAndThePid(): void
	{
		$library = InstrumentHooksFixture::compile(true);
		$recording = InstrumentHooksFixture::load($library);
		$recording->clear_recorded_calls();
		$hooks = FfiInstrumentHooks::load($library);

		$hooks->startBenchmark();
		$hooks->toggleCollect();
		$hooks->toggleCollect();
		$hooks->stopBenchmark();
		$hooks->setExecutedBenchmark('App\FooBench::benchFoo@a');

		$this->assertSame(
			implode("\n", ['init', 'start', 'toggle', 'toggle', 'stop', sprintf('executed %d App\FooBench::benchFoo@a', (int) getmypid())]) . "\n",
			$recording->recorded_calls(),
		);
	}

	/**
	 * @throws CodSpeedException
	 */
	public function testRefusesToCountOutsideTheSimulation(): void
	{
		$this->expectException(CodSpeedException::class);
		$this->expectExceptionMessage("The benchmark does not run under CodSpeed's CPU simulation.");

		FfiInstrumentHooks::load(InstrumentHooksFixture::compile(false));
	}

	/**
	 * @throws CodSpeedException
	 */
	public function testReportsALibraryThatCannotBeLoaded(): void
	{
		$this->expectException(CodSpeedException::class);
		$this->expectExceptionMessage("Cannot load CodSpeed's instrument-hooks library /nonexistent/libinstrument_hooks.so: Failed loading '/nonexistent/libinstrument_hooks.so'");

		FfiInstrumentHooks::load('/nonexistent/libinstrument_hooks.so');
	}

}
