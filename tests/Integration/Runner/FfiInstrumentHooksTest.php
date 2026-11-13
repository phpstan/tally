<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Runner;

use FFI;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tally\Runner\CodSpeedException;
use Tally\Runner\FfiInstrumentHooks;
use function escapeshellarg;
use function exec;
use function getmypid;
use function implode;
use function is_dir;
use function is_file;
use function md5_file;
use function mkdir;
use function rename;
use function sprintf;

final class FfiInstrumentHooksTest extends TestCase
{

	private const SOURCE = __DIR__ . '/../../Fixtures/InstrumentHooks/recording.c';

	/**
	 * @throws CodSpeedException
	 */
	public function testCallsTheLibraryWithTheInstanceAndThePid(): void
	{
		$library = self::compile(true);
		$hooks = FfiInstrumentHooks::load($library);

		$hooks->startBenchmark();
		$hooks->toggleCollect();
		$hooks->toggleCollect();
		$hooks->stopBenchmark();
		$hooks->setExecutedBenchmark('App\FooBench::benchFoo@a');

		$this->assertSame(
			implode("\n", ['init', 'start', 'toggle', 'toggle', 'stop', sprintf('executed %d App\FooBench::benchFoo@a', (int) getmypid())]) . "\n",
			self::recordedCalls($library),
		);
	}

	/**
	 * @throws CodSpeedException
	 */
	public function testRefusesToCountOutsideTheSimulation(): void
	{
		$this->expectException(CodSpeedException::class);
		$this->expectExceptionMessage("The benchmark does not run under CodSpeed's CPU simulation.");

		FfiInstrumentHooks::load(self::compile(false));
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

	/** Compiles once per source change; parallel test processes rename their build into place. */
	private static function compile(bool $instrumented): string
	{
		$directory = __DIR__ . '/../../../tmp/instrument-hooks-fixture';
		if (!is_dir($directory)) {
			@mkdir($directory, 0777, true);
		}
		$library = sprintf('%s/%s-%d.so', $directory, (string) md5_file(self::SOURCE), (int) $instrumented);
		if (is_file($library)) {
			return $library;
		}

		$building = $library . '.' . getmypid();
		exec(sprintf('gcc -shared -fPIC -DINSTRUMENTED=%d -o %s %s 2>&1', (int) $instrumented, escapeshellarg($building), escapeshellarg(self::SOURCE)), $output, $exitCode);
		if ($exitCode !== 0) {
			throw new RuntimeException('Cannot compile the instrument-hooks fixture, is gcc installed? ' . implode("\n", $output));
		}
		rename($building, $library);

		return $library;
	}

	private static function recordedCalls(string $library): string
	{
		return FFI::cdef('const char *recorded_calls(void);', $library)->recorded_calls();
	}

}
