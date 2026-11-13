<?php declare(strict_types = 1);

namespace Tally\Process;

/**
 * Processes that run one after another while each succeeds.
 *
 * @template-covariant T
 */
final class Job
{

	/**
	 * @param T $subject what the job is for, handed back when it finishes
	 * @param non-empty-list<ProcessCommand> $steps
	 */
	public function __construct(
		public readonly mixed $subject,
		public readonly array $steps
	)
	{
	}

}
