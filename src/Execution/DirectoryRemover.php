<?php declare(strict_types = 1);

namespace Tally\Execution;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use function array_filter;
use function is_dir;
use function iterator_to_array;
use function rmdir;
use function unlink;

/**
 * Removes the work directory of a run with what the cases left in their scratch directories.
 * A symbolic link is removed, not what it points to.
 */
final class DirectoryRemover
{

	public function remove(string $directory): void
	{
		if (!is_dir($directory)) {
			return;
		}

		$found = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
		$files = array_filter(iterator_to_array($found, false), static fn (mixed $file): bool => $file instanceof SplFileInfo);
		foreach ($files as $file) {
			if ($file->isDir() && !$file->isLink()) {
				@rmdir($file->getPathname());
			} else {
				@unlink($file->getPathname());
			}
		}
		@rmdir($directory);
	}

}
