<?php declare(strict_types = 1);

namespace Tally\Expression;

/**
 * A function of one number an expression can call, like log(n)
 *
 * @api
 */
interface MathFunction
{

	public function name(): string;

	/**
	 * @throws OutOfDomainException
	 */
	public function apply(float $x): float;

}
