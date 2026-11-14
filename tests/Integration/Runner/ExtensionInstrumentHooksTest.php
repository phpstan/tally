<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Runner;

use Override;
use PHPUnit\Framework\TestCase;
use Tally\Runner\CodSpeedException;
use Tally\Runner\ExtensionInstrumentHooks;
use function get_loaded_extensions;
use function implode;
use function putenv;
use function Tally\Hooks\recorded_calls;

/**
 * Runs with the recording build of tally_hooks, which the Makefile loads into the tests.
 */
final class ExtensionInstrumentHooksTest extends TestCase
{

	#[Override]
	protected function tearDown(): void
	{
		putenv('TALLY_HOOKS_OUTSIDE');
	}

	/**
	 * @throws CodSpeedException
	 */
	public function testCallsTheExtension(): void
	{
		recorded_calls();
		$hooks = ExtensionInstrumentHooks::load(get_loaded_extensions());

		$hooks->startInstrumentation();
		$hooks->toggleCollect();
		$hooks->toggleCollect();
		$hooks->dump('App\FooBench::benchFoo@a');
		$hooks->stopInstrumentation();

		$this->assertSame(implode("\n", ['instrument', 'toggle', 'toggle', 'dump App\FooBench::benchFoo@a', 'stop']) . "\n", recorded_calls());
	}

	/**
	 * @throws CodSpeedException
	 */
	public function testRefusesToCountOutsideTheSimulation(): void
	{
		putenv('TALLY_HOOKS_OUTSIDE=1');

		$this->expectException(CodSpeedException::class);
		$this->expectExceptionMessage("The benchmark does not run under CodSpeed's CPU simulation.");

		ExtensionInstrumentHooks::load(get_loaded_extensions());
	}

	/**
	 * @throws CodSpeedException
	 */
	public function testReportsAnExtensionPhpDidNotLoad(): void
	{
		$this->expectException(CodSpeedException::class);
		$this->expectExceptionMessage("PHP did not load Tally's tally_hooks extension. Its warning is in the output of the process.");

		ExtensionInstrumentHooks::load(['Core', 'posix']);
	}

}
