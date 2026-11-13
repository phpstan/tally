<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use Override;
use Tally\Complexity\ComplexityClass;

/**
 * The instructions grow at most as fast as the size, like a loop over it: #[Linear(size)] is #[Complexity(size, formula: 'n')].
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Linear implements ComplexityClass
{

	private Complexity $complexity;

	public function __construct(string $size)
	{
		$this->complexity = new Complexity($size, 'n');
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
		return 'O(n)';
	}

}
