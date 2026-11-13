<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use Override;
use Tally\Complexity\ComplexityClass;

/**
 * The instructions grow with the logarithm of the size, like a binary search: #[Logarithmic(size)] is #[Complexity(size, formula: 'log(n)')].
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Logarithmic implements ComplexityClass
{

	private Complexity $complexity;

	public function __construct(string $size)
	{
		$this->complexity = new Complexity($size, 'log(n)');
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
		return 'O(log n)';
	}

}
