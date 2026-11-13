<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use Override;
use Tally\Complexity\ComplexityClass;

/**
 * The instructions grow like sorting by comparison: #[Linearithmic(size)] is #[Complexity(size, formula: 'n * log(n)')].
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Linearithmic implements ComplexityClass
{

	private Complexity $complexity;

	public function __construct(string $size)
	{
		$this->complexity = new Complexity($size, 'n * log(n)');
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
		return 'O(n log n)';
	}

}
