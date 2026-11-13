<?php declare(strict_types = 1);

namespace Tally\Expectation;

use LogicException;
use PhpToken;
use function array_values;
use function count;
use function sprintf;

/**
 * PHP code as PHP's tokenizer splits it, with moves that skip whitespace and comments.
 */
final class Tokens
{

	/** @var list<PhpToken> */
	private array $tokens;

	public function __construct(string $code)
	{
		$this->tokens = array_values(PhpToken::tokenize($code));
	}

	public function count(): int
	{
		return count($this->tokens);
	}

	public function at(int $index): PhpToken
	{
		return $this->tokens[$index] ?? throw new LogicException(sprintf('The code has no token %d.', $index));
	}

	/** The index of the next token that is not whitespace or a comment */
	public function next(int $index): int
	{
		do {
			$index++;
		} while ($this->at($index)->isIgnorable());

		return $index;
	}

	/** The index of the previous token that is not whitespace or a comment */
	public function previous(int $index): int
	{
		do {
			$index--;
		} while ($this->at($index)->isIgnorable());

		return $index;
	}

	/** The index of the first token with the text, from $index on */
	public function skipTo(int $index, string $text): int
	{
		while ($this->at($index)->text !== $text) {
			$index++;
		}

		return $index;
	}

}
