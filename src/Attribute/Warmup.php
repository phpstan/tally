<?php declare(strict_types = 1);

namespace PHPStan\Tally\Attribute;

use Attribute;

/**
 * Calls the benchmark method this many times before counting, so the counted call does not
 * include autoloading or filling caches on the first call. On a class, applies to its methods.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Warmup
{

	public function __construct(public readonly int $calls)
	{
	}

}
