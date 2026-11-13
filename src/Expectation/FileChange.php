<?php declare(strict_types = 1);

namespace Tally\Expectation;

/**
 * The new code of a file that keeps expectations, with the cases whose expectations it changes.
 */
final class FileChange
{

	/**
	 * @param list<string> $cases the IDs of the cases
	 */
	public function __construct(
		public readonly string $file,
		public readonly string $before,
		public readonly string $after,
		public readonly array $cases
	)
	{
	}

}
