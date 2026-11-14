<?php declare(strict_types = 1);

namespace Tally\Execution;

use Tally\Runner\CodSpeedException;
use Tally\Runner\ExtensionInstrumentHooks;
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
 * Finds what counting under CodSpeed's CPU simulation needs: the build of CodSpeed's Valgrind and
 * of the tally_hooks extension that Tally's releases include, and the PHP extensions that run
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
	 * @param string $phpBuild which build of tally_hooks the PHP loads, like 8.5 or 8.5-zts
	 * @return array{string, string, string|null} Valgrind's launcher, the directory of its tools,
	 *     and the tally_hooks extension for the workers, null when PHP loads it already
	 * @throws CodSpeedException
	 */
	public function locate(array $extensions, string $phpBuild): array
	{
		if (!in_array('posix', array_map(strtolower(...), $extensions), true)) {
			throw new CodSpeedException(sprintf("Tally needs PHP's POSIX extension.\nSet it up as %s describes.", self::SETUP_URL));
		}

		$valgrind = $this->directory . '/valgrind';
		if (!is_file($valgrind)) {
			throw new CodSpeedException(sprintf(
				"Tally counts with CodSpeed's Valgrind, but has no build of it for %s in %s.\nReleases of Tally include builds for linux-x86_64 and linux-aarch64. In a clone of Tally, build it with make codspeed.",
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

		return [$valgrind, $tools, $this->extension($extensions, $phpBuild)];
	}

	/**
	 * @param list<string> $extensions
	 * @throws CodSpeedException
	 */
	private function extension(array $extensions, string $phpBuild): ?string
	{
		if (in_array(ExtensionInstrumentHooks::EXTENSION, $extensions, true)) {
			return null;
		}

		$extension = sprintf('%s/tally_hooks-%s.so', $this->directory, $phpBuild);
		if (!is_file($extension)) {
			throw new CodSpeedException(sprintf(
				"Tally counts with its tally_hooks extension, but has no build of it for PHP %s in %s.\nReleases of Tally include builds for PHP 8.2 to 8.6, NTS and ZTS, but not debug builds. Build it from ext/tally_hooks with build/build-tally-hooks.sh, and load it in php.ini.",
				$phpBuild,
				$this->directory,
			));
		}

		return $extension;
	}

}
