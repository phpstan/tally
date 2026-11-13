<?php declare(strict_types = 1);

namespace Tally\Report;

use function str_starts_with;
use function strlen;
use function substr;

/** Paths in annotations and patches are relative to the repository, which is the working directory in CI */
final class RelativePath
{

	/**
	 * @return string the file unchanged when it is outside the directory
	 */
	public function of(string $file, string $directory): string
	{
		$prefix = $directory . '/';

		return str_starts_with($file, $prefix) ? substr($file, strlen($prefix)) : $file;
	}

}
