<?php declare(strict_types = 1);

namespace Tally\Expectation;

/**
 * A replacement of a part of the code of a file: the bytes from the offset on, as many as the
 * length, replaced by the text. A length of 0 inserts the text.
 *
 * @api
 */
final class CodeEdit
{

	public function __construct(
		public readonly int $offset,
		public readonly int $length,
		public readonly string $text
	)
	{
	}

}
