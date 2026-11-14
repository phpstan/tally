<?php declare(strict_types = 1);

namespace Tally\Tests;

use Tally\Attribute\Linear;
use Tally\CaseId;
use Tally\Complexity\GrowthPoint;
use Tally\Expectation\ComplexityAttribute;
use Tally\Expectation\ExpectedAttributeRewriter;

/** Growth points of a #[Linear('numbers')] benchmark, for tests */
final class LinearGrowth
{

	public static function point(CaseId $base, float $size, float $growth): GrowthPoint
	{
		$rewriter = new ExpectedAttributeRewriter();

		return new GrowthPoint(
			new ComplexityAttribute($rewriter, '/bench/ABench.php', $rewriter->index(''), $base->class, $base->method, new Linear('numbers')),
			$base,
			$size,
			$growth,
		);
	}

}
