<?php declare(strict_types = 1);

namespace PHPStan\Tally\Cli;

use function fwrite;

final class Output
{

	/**
	 * @param resource $stdout
	 * @param resource $stderr
	 */
	public function __construct(private $stdout, private $stderr)
	{
	}

	public function write(string $text): void
	{
		fwrite($this->stdout, $text);
	}

	public function writeError(string $text): void
	{
		fwrite($this->stderr, $text);
	}

}
