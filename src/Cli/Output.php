<?php declare(strict_types = 1);

namespace Tally\Cli;

use function fwrite;
use function preg_replace;

final class Output
{

	/**
	 * @param resource $stdout
	 * @param resource $stderr
	 * @param bool $colors whether to color the standard output
	 * @param bool $errorColors whether to color the error output
	 */
	public function __construct(private $stdout, private $stderr, public readonly bool $colors, private bool $errorColors)
	{
	}

	public function write(string $text): void
	{
		fwrite($this->stdout, $text);
	}

	/**
	 * Writes an error to the error output. Its first line says what is wrong and is bold red,
	 * the lines after it say what to do about it.
	 */
	public function error(string $error): void
	{
		if ($this->errorColors) {
			$error = preg_replace('~^.*~', "\e[1;31m\$0\e[0m", $error);
		}

		fwrite($this->stderr, $error . "\n");
	}

}
