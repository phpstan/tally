<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use Override;
use Tally\Complexity\ComplexityClass;

/**
 * The instructions double with each element of the size, like trying every subset: #[Exponential(size)] is #[Complexity(size, formula: '2^n')].
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Exponential implements ComplexityClass
{

	private Complexity $complexity;

	public function __construct(string $size)
	{
		$this->complexity = new Complexity($size, '2^n');
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
		return 'O(2^n)';
	}

}
