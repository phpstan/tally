<?php declare(strict_types = 1);

namespace Tally\Discovery;

use RuntimeException;
use function sprintf;

final class InvalidBenchmarkException extends RuntimeException
{

	public function __construct(string $where, string $problem)
	{
		parent::__construct(sprintf('%s: %s', $where, $problem));
	}

}
