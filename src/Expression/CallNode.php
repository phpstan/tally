<?php declare(strict_types = 1);

namespace Tally\Expression;

use Override;

final class CallNode implements Node
{

	/**
	 * @param list<Node> $arguments
	 */
	public function __construct(public readonly string $name, public readonly array $arguments, public readonly Span $nameSpan, private Span $span)
	{
	}

	#[Override]
	public function span(): Span
	{
		return $this->span;
	}

}
