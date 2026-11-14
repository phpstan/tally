<?php declare(strict_types = 1);

namespace Tally\Process;

use function array_shift;
use function explode;
use function strrpos;
use function substr;

/**
 * Reads the process table of Linux: which process started which. A benchmark may start processes
 * that keep running when the worker alone is killed, so a process that runs too long is killed
 * with all its descendants.
 */
final class ProcessTable
{

	/**
	 * @param array<int, string|false> $stats the contents of /proc/<pid>/stat by pid, false for a
	 *     process that ended before it was read
	 * @return array<int, int> the parent of each process
	 */
	public function parents(array $stats): array
	{
		$parents = [];
		foreach ($stats as $pid => $stat) {
			$parentPid = $stat === false ? null : $this->parentPid($stat);
			if ($parentPid === null) {
				continue;
			}

			$parents[$pid] = $parentPid;
		}

		return $parents;
	}

	/**
	 * @param string $stat pid (command) state parent-pid ...
	 */
	private function parentPid(string $stat): ?int
	{
		// the command can contain spaces and parentheses, the last ) ends it
		$commandEnd = strrpos($stat, ')');
		if ($commandEnd === false) {
			return null;
		}

		$fields = explode(' ', substr($stat, $commandEnd + 2));

		return isset($fields[1]) && $fields[1] !== '' ? (int) $fields[1] : null;
	}

	/**
	 * @param array<int, int> $parents the parent of each process
	 * @return list<int> the descendants, children before grandchildren
	 */
	public function descendants(array $parents, int $pid): array
	{
		$descendants = [];
		$queue = [$pid];
		while ($queue !== []) {
			$parent = array_shift($queue);
			foreach ($parents as $child => $childParent) {
				if ($childParent !== $parent) {
					continue;
				}

				$descendants[] = $child;
				$queue[] = $child;
			}
		}

		return $descendants;
	}

}
