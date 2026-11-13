<?php declare(strict_types = 1);

namespace Tally\Execution;

use Tally\Runner\CodSpeedException;
use function array_map;
use function explode;
use function in_array;
use function is_executable;
use function is_file;
use function sprintf;
use function strtolower;
use const PATH_SEPARATOR;

/**
 * Finds what counting under CodSpeed's CPU simulation needs: the codspeed CLI, CodSpeed's
 * instrument-hooks library, and the PHP extensions that run the workers.
 */
final class CodSpeedInstallation
{

	public const INSTRUMENT_HOOKS_VARIABLE = 'INSTRUMENT_HOOKS_LIBRARY';

	public const SETUP_URL = 'https://github.com/phpstan/tally#setting-up-codspeed';

	/**
	 * @param array<string, string> $environment
	 * @param list<string> $extensions the loaded PHP extensions
	 * @return array{string, string} the codspeed CLI and the instrument-hooks library
	 * @throws CodSpeedException
	 */
	public function locate(array $environment, array $extensions): array
	{
		foreach (['ffi' => 'FFI', 'posix' => 'POSIX'] as $extension => $name) {
			if (!in_array($extension, array_map(strtolower(...), $extensions), true)) {
				throw new CodSpeedException(sprintf("Tally needs PHP's %s extension. %s", $name, $this->setUp()));
			}
		}

		return [$this->codspeed($environment), $this->instrumentHooks($environment)];
	}

	/**
	 * @param array<string, string> $environment
	 * @throws CodSpeedException
	 */
	private function codspeed(array $environment): string
	{
		foreach (explode(PATH_SEPARATOR, $environment['PATH'] ?? '') as $directory) {
			$codspeed = $directory . '/codspeed';
			if ($directory !== '' && is_file($codspeed) && is_executable($codspeed)) {
				return $codspeed;
			}
		}

		throw new CodSpeedException(sprintf("Tally counts with CodSpeed's CPU simulation, but the codspeed CLI is not on PATH. %s", $this->setUp()));
	}

	/**
	 * @param array<string, string> $environment
	 * @throws CodSpeedException
	 */
	private function instrumentHooks(array $environment): string
	{
		$library = $environment[self::INSTRUMENT_HOOKS_VARIABLE] ?? '';
		if ($library === '') {
			throw new CodSpeedException(sprintf("Tally counts with CodSpeed's CPU simulation, but %s is not set to CodSpeed's instrument-hooks library. %s", self::INSTRUMENT_HOOKS_VARIABLE, $this->setUp()));
		}
		if (!is_file($library)) {
			throw new CodSpeedException(sprintf('%s is set to %s, which does not exist. %s', self::INSTRUMENT_HOOKS_VARIABLE, $library, $this->setUp()));
		}

		return $library;
	}

	private function setUp(): string
	{
		return sprintf('See %s', self::SETUP_URL);
	}

}
