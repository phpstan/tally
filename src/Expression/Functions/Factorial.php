<?php declare(strict_types = 1);

namespace Tally\Expression\Functions;

use Override;
use Tally\Expression\MathFunction;
use Tally\Expression\OutOfDomainException;
use function fmod;
use const INF;

final class Factorial implements MathFunction
{

	/** 171! is larger than the largest float */
	private const LARGEST_FINITE = 170;

	#[Override]
	public function name(): string
	{
		return 'factorial';
	}

	#[Override]
	public function apply(float $x): float
	{
		if ($x < 0 || fmod($x, 1.0) !== 0.0) {
			throw new OutOfDomainException('a whole number from 0');
		}
		if ($x > self::LARGEST_FINITE) {
			return INF;
		}

		return $this->product($x);
	}

	private function product(float $x): float
	{
		return $x === 0.0 ? 1.0 : $x * $this->product($x - 1);
	}

}
