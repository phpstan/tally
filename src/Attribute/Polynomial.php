<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use InvalidArgumentException;
use Override;
use Tally\Complexity\ComplexityClass;
use function sprintf;

/**
 * The instructions grow with the size to the power of the degree: #[Polynomial(size, degree: 3)]
 * is #[Complexity(size, formula: 'n^3')].
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Polynomial implements ComplexityClass
{

	private Complexity $complexity;

	/**
	 * @throws InvalidArgumentException
	 */
	public function __construct(string $size, private int $degree)
	{
		if ($degree < 1) {
			throw new InvalidArgumentException(sprintf('#[Polynomial] needs a degree from 1, %d given. Use #[Constant] for 0.', $degree));
		}

		$this->complexity = new Complexity($size, 'n^' . $degree);
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
		return sprintf('O(n^%d)', $this->degree);
	}

}
