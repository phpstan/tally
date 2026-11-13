<?php declare(strict_types = 1);

namespace Tally\Expression;

use function substr;

/** Where a token or a node is in the source of an expression, in bytes */
final class Span
{

	public function __construct(public readonly int $offset, public readonly int $length)
	{
	}

	/** The span from the start of this one to the end of the other */
	public function to(self $other): self
	{
		return new self($this->offset, $other->offset + $other->length - $this->offset);
	}

	public function text(string $source): string
	{
		return substr($source, $this->offset, $this->length);
	}

}
