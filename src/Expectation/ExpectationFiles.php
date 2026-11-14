<?php declare(strict_types = 1);

namespace Tally\Expectation;

use RuntimeException;
use function file_get_contents;
use function file_put_contents;
use function sprintf;

/**
 * Reads and writes the files that keep the expectations, for ExpectationChanges.
 */
final class ExpectationFiles
{

	/**
	 * @param list<string> $files
	 * @return array<string, string|false> their code by path, false for a file that cannot be read
	 */
	public function read(array $files): array
	{
		$code = [];
		foreach ($files as $file) {
			$code[$file] ??= @file_get_contents($file);
		}

		return $code;
	}

	/**
	 * @throws RuntimeException
	 */
	public function write(FileChange $change): void
	{
		if (@file_put_contents($change->file, $change->after) === false) {
			throw new RuntimeException(sprintf('Cannot write %s.', $change->file));
		}
	}

}
