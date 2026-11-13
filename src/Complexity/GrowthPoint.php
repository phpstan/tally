<?php declare(strict_types = 1);

namespace Tally\Complexity;

use Tally\CaseId;

/**
 * Where a case of a benchmark with a complexity class is on its growth curve: how many times its
 * formula is larger than at the base, the data set of the smallest size, which it is compared with.
 */
final class GrowthPoint
{

	public function __construct(
		public readonly string $bound,
		public readonly CaseId $base,
		public readonly float $growth
	)
	{
	}

}
