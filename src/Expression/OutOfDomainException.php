<?php declare(strict_types = 1);

namespace Tally\Expression;

use Exception;

/** A function got an argument it is not defined for, like log(0) */
final class OutOfDomainException extends Exception
{

	/**
	 * @param string $needs what the function needs, such as "a number above 0"
	 */
	public function __construct(public readonly string $needs)
	{
		parent::__construct($needs);
	}

}
