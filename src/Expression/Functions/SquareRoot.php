<?php declare(strict_types = 1);

namespace Tally\Expression\Functions;

use Override;
use Tally\Expression\MathFunction;
use Tally\Expression\OutOfDomainException;
use function sqrt;

final class SquareRoot implements MathFunction
{

	#[Override]
	public function name(): string
	{
		return 'sqrt';
	}

	#[Override]
	public function apply(float $x): float
	{
		if ($x < 0) {
			throw new OutOfDomainException('a number from 0');
		}

		return sqrt($x);
	}

}
