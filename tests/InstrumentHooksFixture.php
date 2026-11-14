<?php declare(strict_types = 1);

namespace Tally\Tests;

use FFI;
use RuntimeException;
use Tally\Execution\DirectoryRemover;
use function chmod;
use function copy;
use function dirname;
use function escapeshellarg;
use function exec;
use function getmypid;
use function implode;
use function is_dir;
use function is_file;
use function md5;
use function md5_file;
use function mkdir;
use function rename;
use function sprintf;

/**
 * Builds Fixtures/InstrumentHooks/recording.c, which stands in for CodSpeed's instrument-hooks
 * library and records the calls of the process that loaded it.
 */
final class InstrumentHooksFixture
{

	private const SOURCE = __DIR__ . '/Fixtures/InstrumentHooks/recording.c';

	private const VALGRIND = __DIR__ . '/Fixtures/CodSpeed/valgrind';

	/** Compiles once per source change; parallel test processes rename their build into place. */
	public static function compile(bool $instrumented): string
	{
		$directory = __DIR__ . '/../tmp/instrument-hooks-fixture';
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

	/**
	 * A build of CodSpeed's Valgrind for the tests, as bin/tally finds one in codspeed/: the fake
	 * Fixtures/CodSpeed/valgrind, a directory for its tools, and the library that records calls.
	 */
	public static function codspeed(): string
	{
		$library = self::compile(true);
		$directory = sprintf('%s/codspeed-%s/linux-fake', dirname($library), md5($library . (string) md5_file(self::VALGRIND)));
		if (is_dir($directory)) {
			return $directory;
		}

		$building = $directory . '.' . getmypid();
		@mkdir($building . '/libexec', 0777, true);
		copy(self::VALGRIND, $building . '/valgrind');
		chmod($building . '/valgrind', 0755);
		copy($library, $building . '/libinstrument_hooks.so');
		if (!@rename($building, $directory)) {
			// another test process built it first
			(new DirectoryRemover())->remove($building);
		}

		return $directory;
	}

	/**
	 * Loads the library to read and clear its calls. PHP unloads a library with its last FFI
	 * object, and with it the calls, so a test keeps this one while the code under test runs.
	 */
	public static function load(string $library): FFI
	{
		return FFI::cdef('const char *recorded_calls(void); void clear_recorded_calls(void);', $library);
	}

}
