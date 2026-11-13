<?php declare(strict_types = 1);

namespace Tally\Expression;

use Override;

final class NumberNode implements Node
{

	public function __construct(public readonly int|float $value, private Span $span)
	{
	}

	#[Override]
	public function span(): Span
	{
		return $this->span;
	}

}
