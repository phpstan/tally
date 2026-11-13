<?php declare(strict_types = 1);

namespace Tally\Quantity;

use Exception;

final class InvalidQuantityException extends Exception
{

	public function __construct(string $message)
	{
		parent::__construct($message);
	}

}
