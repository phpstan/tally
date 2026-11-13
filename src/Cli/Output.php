<?php declare(strict_types = 1);

namespace Tally\Cli;

use function fwrite;

final class Output
{

	/**
	 * @param resource $stdout
	 * @param resource $stderr
	 * @param bool $colors whether to color the standard output
	 */
	public function __construct(private $stdout, private $stderr, public readonly bool $colors)
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
