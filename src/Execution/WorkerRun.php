<?php declare(strict_types = 1);

namespace Tally\Execution;

use Tally\Measurement;
use Tally\Process\ProcessResult;

/**
 * What one worker process of a case left behind.
 */
final class WorkerRun
{

	/**
	 * @param string $name what the process was in failures, like The native run
	 * @param Measurement|null $written what the worker wrote, null when it wrote nothing
	 * @param string $output the output of the process
	 */
	public function __construct(
		public readonly string $name,
		public readonly ProcessResult $result,
		public readonly ?Measurement $written,
		public readonly string $output
	)
	{
	}

}
