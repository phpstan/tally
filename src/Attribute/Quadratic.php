<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use Override;
use Tally\Complexity\ComplexityClass;

/**
 * The instructions grow with the square of the size, like comparing every pair: #[Quadratic(size)] is #[Complexity(size, formula: 'n^2')].
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Quadratic implements ComplexityClass
{

	private Complexity $complexity;

	public function __construct(string $size)
	{
		$this->complexity = new Complexity($size, 'n^2');
	}

	#[Override]
	public function size(): string
	{
		return $this->complexity->size();
	}

	#[Override]
	public function formula(): string
	{
		return $this->complexity->formula();
	}

	#[Override]
	public function describe(): string
	{
		return 'O(n^2)';
	}

}
