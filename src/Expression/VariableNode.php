<?php declare(strict_types = 1);

namespace Tally\Expression;

use Override;

final class VariableNode implements Node
{

	public function __construct(public readonly string $name, private Span $span)
	{
	}

	#[Override]
	public function span(): Span
	{
		return $this->span;
	}

}
