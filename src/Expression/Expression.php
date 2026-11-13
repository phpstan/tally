<?php declare(strict_types = 1);

namespace Tally\Expression;

/** A parsed expression with the source it was parsed from, which error messages quote */
final class Expression
{

	public function __construct(public readonly string $source, public readonly Node $node)
	{
	}

}
