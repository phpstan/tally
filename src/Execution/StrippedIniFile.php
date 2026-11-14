<?php declare(strict_types = 1);

namespace Tally\Execution;

use RuntimeException;
use function array_map;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function sprintf;

/**
 * Writes the php.ini of the workers without the stripped extensions, composed of the ini files that
 * PHP loaded for tally run, which are those it loads for the workers.
 */
final class StrippedIniFile
{

	/**
	 * @param list<string> $iniFiles the ini files PHP loaded, in the order it loaded them
	 */
	public function __construct(private StrippedIni $ini, private array $iniFiles)
	{
	}

	/**
	 * @param list<string> $extensions the names of the extensions to strip
	 * @return string|null the php.ini written into the directory, null when no ini file loads any of
	 *     the extensions, and the workers start with PHP's own
	 * @throws RuntimeException when the php.ini cannot be written
	 */
	public function write(array $extensions, string $directory): ?string
	{
		$ini = $this->ini->compose(array_map(static fn (string $file): string => (string) file_get_contents($file), $this->iniFiles), $extensions);
		if ($ini === null) {
			return null;
		}

		$file = $directory . '/php.ini';
		// writing the file fails when the directory cannot be created
		@mkdir($directory, 0777, true);
		if (@file_put_contents($file, $ini) === false) {
			throw new RuntimeException(sprintf('Cannot write %s.', $file));
		}

		return $file;
	}

}
