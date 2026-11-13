<?php declare(strict_types = 1);

namespace Tally\Expression;

/** The tokens of an expression, followed by End */
final class TokenStream
{

	/**
	 * @param list<Token> $tokens without End
	 */
	public function __construct(private array $tokens, private Token $end)
	{
	}

	/** The token at the position, or End after the last one */
	public function at(int $position): Token
	{
		return $this->tokens[$position] ?? $this->end;
	}

	/**
	 * @return non-empty-list<Token>
	 */
	public function all(): array
	{
		return [...$this->tokens, $this->end];
	}

}
