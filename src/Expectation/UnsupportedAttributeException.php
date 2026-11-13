<?php declare(strict_types = 1);

namespace Tally\Expectation;

use Exception;
use function sprintf;

final class UnsupportedAttributeException extends Exception
{

	public function __construct(int $line)
	{
		parent::__construct(sprintf('#[ExpectedFor] on line %d: write the data set as a string literal, so tally accept can find the attribute.', $line));
	}

}
