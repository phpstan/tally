<?php declare(strict_types = 1);

namespace Tally\Expression;

use function max;
use function strlen;

final class Token
{

	public function __construct(public readonly TokenType $type, public readonly string $text, public readonly int $offset)
	{
	}

	public function span(): Span
	{
		// End has no text, its span is the place after the expression
		return new Span($this->offset, max(1, strlen($this->text)));
	}

}
