<?php declare(strict_types = 1);

namespace Tally\Expression\Functions;

use Override;
use Tally\Expression\MathFunction;
use function exp;

final class Exponential implements MathFunction
{

	#[Override]
	public function name(): string
	{
		return 'exp';
	}

	#[Override]
	public function apply(float $x): float
	{
		return exp($x);
	}

}
