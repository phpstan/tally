<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use Override;
use Tally\Complexity\ComplexityClass;
use function sprintf;

/**
 * Checks how the instructions grow with the size of the data sets, instead of expecting numbers:
 * #[Complexity(size: 'numbers', formula: 'n * log(n)')] compares each data set with the smallest
 * one, and fails when the instructions grew more than the formula allows. The size is an
 * expression of the method's parameters, a number or an array, whose count is the size. The
 * presets, like #[Linear], are this attribute with a formula.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Complexity implements ComplexityClass
{

	public function __construct(private string $size, private string $formula)
	{
	}

	#[Override]
	public function size(): string
	{
		return $this->size;
	}

	#[Override]
	public function formula(): string
	{
		return $this->formula;
	}

	#[Override]
	public function describe(): string
	{
		return sprintf('O(%s)', $this->formula);
	}

}
