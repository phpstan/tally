<?php declare(strict_types = 1);

namespace Tally\Quantity;

use Exception;
use function array_filter;
use function implode;
use function sprintf;

final class InvalidQuantityException extends Exception
{

	/**
	 * @param list<string> $units
	 */
	public function __construct(string $text, array $units)
	{
		parent::__construct(sprintf('"%s" is not a quantity, such as 3.119B or 44.49MiB. Units: %s.', $text, implode(', ', array_filter($units, static fn (string $unit): bool => $unit !== ''))));
	}

}
