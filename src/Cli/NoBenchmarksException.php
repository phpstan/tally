<?php declare(strict_types = 1);

namespace Tally\Cli;

use Exception;

/** The paths a command got have no benchmarks in them */
final class NoBenchmarksException extends Exception
{

	public function __construct(string $message)
	{
		parent::__construct($message);
	}

}
