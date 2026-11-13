<?php declare(strict_types = 1);

namespace Tally\Expression;

use Override;

/** A key of an array: input.items, input[0], input['a b'] */
final class KeyNode implements Node
{

	public function __construct(public readonly Node $array, public readonly int|string $key, public readonly Span $keySpan, private Span $span)
	{
	}

	#[Override]
	public function span(): Span
	{
		return $this->span;
	}

}
