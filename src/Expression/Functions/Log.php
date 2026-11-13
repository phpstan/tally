<?php declare(strict_types = 1);

namespace Tally\Expression\Functions;

use Override;
use Tally\Expression\MathFunction;
use Tally\Expression\OutOfDomainException;
use function log;

/** The natural logarithm. The base does not change how a cost grows, so there is one log. */
final class Log implements MathFunction
{

	#[Override]
	public function name(): string
	{
		return 'log';
	}

	#[Override]
	public function apply(float $x): float
	{
		if ($x <= 0) {
			throw new OutOfDomainException('a number above 0');
		}

		return log($x);
	}

}
