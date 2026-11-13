<?php declare(strict_types = 1);

namespace Tally\Expectation;

/** @api */
final class SourceLocation
{

	public function __construct(public readonly string $file, public readonly int $line)
	{
	}

}
