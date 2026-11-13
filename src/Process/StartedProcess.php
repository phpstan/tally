<?php declare(strict_types = 1);

namespace Tally\Process;

/**
 * A process the pool started and has not seen end yet.
 */
final class StartedProcess
{

	/**
	 * @param resource $process from proc_open()
	 * @param float $startedAt seconds from hrtime()
	 */
	public function __construct(
		public readonly ProcessCommand $command,
		public readonly mixed $process,
		public readonly float $startedAt
	)
	{
	}

}
