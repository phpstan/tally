<?php declare(strict_types = 1);

namespace Tally\Expression;

use Exception;
use function sprintf;
use function str_repeat;
use function strcspn;
use function substr;

/**
 * A mistake in an expression a benchmark author wrote. The message says what is wrong on its
 * first line, and shows the expression with the part that is wrong underlined on the next two.
 */
final class ExpressionException extends Exception
{

	public function __construct(string $problem, string $source, Span $span)
	{
		// only a newline the lexer rejects can be in the source, and the problem is never after it
		$line = substr($source, 0, strcspn($source, "\n"));
		$padding = '';
		for ($i = 0; $i < $span->offset; $i++) {
			// a tab stays a tab, so the carets line up under the problem
			$padding .= $line[$i] === "\t" ? "\t" : ' ';
		}

		parent::__construct(sprintf("%s\n  %s\n  %s%s", $problem, $line, $padding, str_repeat('^', $span->length)));
	}

}
