<?php declare(strict_types = 1);

namespace PHPStan\Tally;

use PHPStan\Tally\Expectation\ExpectationSource;

/**
 * One call of a benchmark method with one data set, the unit Tally counts, lists, and shards.
 */
final class BenchmarkCase
{

	/**
	 * @param list<mixed> $arguments
	 * @param list<string> $beforeMethods
	 * @param list<string> $afterMethods
	 */
	public function __construct(
		public readonly CaseId $id,
		public readonly array $arguments,
		public readonly int $warmup,
		public readonly array $beforeMethods,
		public readonly array $afterMethods,
		public readonly ExpectationSource $expectations
	)
	{
	}

}
