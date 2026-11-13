<?php declare(strict_types = 1);

namespace Tally\Process;

/**
 * How a process ended: with an exit code, or killed because it ran longer than its time limit.
 */
final class ProcessResult
{

	/** Added to the number of the signal that killed a process, like shells do */
	public const SIGNAL_EXIT_CODE_OFFSET = 128;

	private function __construct(
		public readonly ProcessCommand $command,
		public readonly int $exitCode,
		public readonly bool $timedOut
	)
	{
	}

	public static function exited(ProcessCommand $command, int $exitCode): self
	{
		return new self($command, $exitCode, false);
	}

	public static function timedOut(ProcessCommand $command): self
	{
		return new self($command, self::SIGNAL_EXIT_CODE_OFFSET + 9, true);
	}

	public function succeeded(): bool
	{
		return !$this->timedOut && $this->exitCode === 0;
	}

}
