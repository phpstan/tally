<?php declare(strict_types = 1);

namespace Tally\Expectation;

/**
 * What accepting measured numbers changes: the files, and the cases whose expectations cannot be
 * rewritten, with why.
 */
final class ChangePlan
{

	/**
	 * @param list<FileChange> $files in the order of the first case of each file
	 * @param array<string, string> $failures the reasons by case ID
	 */
	public function __construct(
		public readonly array $files,
		public readonly array $failures
	)
	{
	}

}
