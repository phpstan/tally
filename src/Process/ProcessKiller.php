<?php declare(strict_types = 1);

namespace Tally\Process;

use function basename;
use function dirname;
use function file_get_contents;
use function glob;
use function posix_kill;

/**
 * Kills a process and all its descendants, which it finds in /proc before killing any of them,
 * because killing a process hands its children over to init.
 */
final class ProcessKiller
{

	/** SIGKILL, whose constant only the pcntl extension defines, which many PHP builds lack */
	private const SIGKILL = 9;

	public function __construct(private ProcessTable $table)
	{
	}

	public function kill(int $pid): void
	{
		$stats = [];
		$files = glob('/proc/[0-9]*/stat');
		foreach ($files === false ? [] : $files as $file) {
			// the process may have ended since glob() found it
			$stats[(int) basename(dirname($file))] = @file_get_contents($file);
		}

		foreach ([$pid, ...$this->table->descendants($this->table->parents($stats), $pid)] as $process) {
			posix_kill($process, self::SIGKILL);
		}
	}

}
