<?php declare(strict_types = 1);

namespace Tally\Expression;

use Override;

final class BinaryNode implements Node
{

	public function __construct(public readonly Operator $operator, public readonly Node $left, public readonly Node $right, private Span $span)
	{
	}

	#[Override]
	public function span(): Span
	{
		return $this->span;
	}

}
