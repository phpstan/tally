<?php declare(strict_types = 1);

namespace Tally\Expectation;

use RuntimeException;
use function sprintf;

final class MethodNotFoundException extends RuntimeException
{

	public function __construct(string $class, string $method)
	{
		parent::__construct(sprintf('Method %s::%s() is not declared in this file.', $class, $method));
	}

}
