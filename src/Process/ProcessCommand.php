<?php declare(strict_types = 1);

namespace Tally\Process;

/**
 * A process to start: its arguments, which are not passed through a shell, the file its output
 * goes to, and how long it may run.
 */
final class ProcessCommand
{

	/**
	 * @param non-empty-list<string> $arguments the program first
	 * @param string $logFile gets both the standard and the error output
	 */
	public function __construct(
		public readonly array $arguments,
		public readonly string $logFile,
		public readonly int $timeLimitSeconds
	)
	{
	}

}
