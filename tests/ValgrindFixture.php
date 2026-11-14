<?php declare(strict_types = 1);

namespace Tally\Tests;

use Tally\Execution\DirectoryRemover;
use function chmod;
use function copy;
use function getmypid;
use function is_dir;
use function md5_file;
use function mkdir;
use function rename;
use function sprintf;

/**
 * A build of CodSpeed's Valgrind for the tests, as bin/tally finds one in codspeed/: the fake
 * Fixtures/CodSpeed/valgrind and a directory for its tools. It has no build of tally_hooks: the
 * tests run with the recording build loaded, see the Makefile.
 */
final class ValgrindFixture
{

	private const VALGRIND = __DIR__ . '/Fixtures/CodSpeed/valgrind';

	/** Copies once per change of the fake; parallel test processes rename their copy into place. */
	public static function codspeed(): string
	{
		$directory = sprintf('%s/../tmp/valgrind-fixture/%s/linux-fake', __DIR__, (string) md5_file(self::VALGRIND));
		if (is_dir($directory)) {
			return $directory;
		}

		$building = $directory . '.' . getmypid();
		@mkdir($building . '/libexec', 0777, true);
		copy(self::VALGRIND, $building . '/valgrind');
		chmod($building . '/valgrind', 0755);
		if (!@rename($building, $directory)) {
			// another test process copied it first
			(new DirectoryRemover())->remove($building);
		}

		return $directory;
	}

}
