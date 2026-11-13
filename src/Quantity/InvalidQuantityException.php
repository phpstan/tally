<?php declare(strict_types = 1);

namespace PHPStan\Tally\Quantity;

use InvalidArgumentException;
use function implode;
use function sprintf;

final class InvalidQuantityException extends InvalidArgumentException
{

	/**
	 * @param list<string> $units
	 */
	public function __construct(string $text, array $units)
	{
		parent::__construct(sprintf('"%s" is not a quantity, such as 3.119B or 44.49MiB. Units: %s.', $text, implode(', ', $units)));
	}

}
