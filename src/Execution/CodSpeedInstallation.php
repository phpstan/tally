<?php declare(strict_types = 1);

namespace Tally\Execution;

use Tally\Runner\CodSpeedException;
use function array_map;
use function basename;
use function chmod;
use function dirname;
use function glob;
use function in_array;
use function is_file;
use function sprintf;
use function strtolower;

/**
 * Finds what counting under CodSpeed's CPU simulation needs: the build of CodSpeed's Valgrind
 * and instrument-hooks library that Tally's releases include, and the PHP extensions that run
 * the workers.
 */
final class CodSpeedInstallation
{

	public const SETUP_URL = 'https://github.com/phpstan/tally#installation';

	/**
	 * @param string $directory the build for this platform, like codspeed/linux-x86_64 in Tally
	 */
	public function __construct(private string $directory)
	{
	}

	/**
	 * @param list<string> $extensions the loaded PHP extensions
	 * @return array{string, string, string} Valgrind's launcher, the directory of its tools, and
	 *     the instrument-hooks library
	 * @throws CodSpeedException
	 */
	public function locate(array $extensions): array
	{
		foreach (['ffi' => 'FFI', 'posix' => 'POSIX'] as $extension => $name) {
			if (!in_array($extension, array_map(strtolower(...), $extensions), true)) {
				throw new CodSpeedException(sprintf("Tally needs PHP's %s extension.\nSet it up as %s describes.", $name, self::SETUP_URL));
			}
		}

		$valgrind = $this->directory . '/valgrind';
		if (!is_file($valgrind)) {
			throw new CodSpeedException(sprintf(
				"Tally counts with CodSpeed's Valgrind, but has no build of it for %s in %s.\nReleases of Tally include builds for linux-x86_64 and linux-aarch64. In a clone of Tally, build it with build/build-codspeed.sh.",
				basename($this->directory),
				dirname($this->directory),
			));
		}

		// Composer drops the executable bits when it extracts a package without unzip
		$tools = $this->directory . '/libexec';
		$toolFiles = glob($tools . '/*-linux');
		foreach ([$valgrind, ...($toolFiles === false ? [] : $toolFiles)] as $executable) {
			@chmod($executable, 0755);
		}

		return [$valgrind, $tools, $this->directory . '/libinstrument_hooks.so'];
	}

}
