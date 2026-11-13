<?php declare(strict_types = 1);

namespace Tally;

use Tally\Comparison\TolerancePolicy;
use Tally\Complexity\GrowthPoint;
use Tally\Expectation\ExpectationSource;

/**
 * One call of a benchmark method with one data set, the unit Tally counts, lists, and shards.
 */
final class BenchmarkCase
{

	/**
	 * @param list<mixed> $arguments
	 * @param list<string> $beforeMethods
	 * @param list<string> $afterMethods
	 * @param GrowthPoint|null $growth null for a case compared with expected numbers
	 */
	public function __construct(
		public readonly CaseId $id,
		public readonly array $arguments,
		public readonly int $warmup,
		public readonly array $beforeMethods,
		public readonly array $afterMethods,
		public readonly ExpectationSource $expectations,
		public readonly TolerancePolicy $tolerance,
		public readonly ?GrowthPoint $growth
	)
	{
	}

	/** The case compared by how its instructions grow, instead of by expected numbers */
	public function withGrowth(GrowthPoint $growth): self
	{
		return new self($this->id, $this->arguments, $this->warmup, $this->beforeMethods, $this->afterMethods, $this->expectations, $this->tolerance, $growth);
	}

}
