<?php declare(strict_types = 1);

namespace Tally\Expression;

use Override;

final class NegateNode implements Node
{

	public function __construct(public readonly Node $operand, private Span $span)
	{
	}

	#[Override]
	public function span(): Span
	{
		return $this->span;
	}

}
