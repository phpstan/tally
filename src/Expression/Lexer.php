<?php declare(strict_types = 1);

namespace Tally\Expression;

use function addcslashes;
use function filter_var;
use function preg_match;
use function sprintf;
use function str_contains;
use function strlen;
use const FILTER_VALIDATE_INT;

/** Splits the source of an expression into tokens, the last of which is End */
final class Lexer
{

	private const PATTERN = '/\G(?:(?<space>[ \t]+)|(?<number>[0-9]+(?:\.[0-9]+)?)|(?<name>[A-Za-z_][A-Za-z0-9_]*)|(?<string>\'[^\']*\'|"[^"]*"))/';

	private const SYMBOLS = [
		'+' => TokenType::Plus,
		'-' => TokenType::Minus,
		'*' => TokenType::Star,
		'/' => TokenType::Slash,
		'^' => TokenType::Caret,
		'(' => TokenType::LeftParen,
		')' => TokenType::RightParen,
		'[' => TokenType::LeftBracket,
		']' => TokenType::RightBracket,
		'.' => TokenType::Dot,
		',' => TokenType::Comma,
	];

	/**
	 * @throws ExpressionException
	 */
	public function tokenize(string $source): TokenStream
	{
		$tokens = [];
		$offset = 0;
		while ($offset < strlen($source)) {
			$symbol = self::SYMBOLS[$source[$offset]] ?? null;
			if ($symbol !== null) {
				$tokens[] = new Token($symbol, $source[$offset], $offset);
				$offset++;
				continue;
			}
			if (preg_match(self::PATTERN, $source, $match, 0, $offset) !== 1) {
				throw $this->unexpected($source, $offset);
			}

			$text = $match[0];
			$type = match (true) {
				($match['string'] ?? '') !== '' => TokenType::String,
				($match['name'] ?? '') !== '' => TokenType::Name,
				($match['number'] ?? '') !== '' => TokenType::Number,
				default => null,
			};
			if ($type === TokenType::Number && !str_contains($text, '.') && filter_var($text, FILTER_VALIDATE_INT) === false) {
				throw new ExpressionException(sprintf('The number %s is too large.', $text), $source, new Span($offset, strlen($text)));
			}
			if ($type !== null) {
				$tokens[] = new Token($type, $text, $offset);
			}

			$offset += strlen($text);
		}

		return new TokenStream($tokens, new Token(TokenType::End, '', $offset));
	}

	private function unexpected(string $source, int $offset): ExpressionException
	{
		$character = $source[$offset];
		if ($character === '\'' || $character === '"') {
			return new ExpressionException(
				sprintf('The string is not closed, end it with %s.', $character),
				$source,
				new Span($offset, strlen($source) - $offset),
			);
		}

		return new ExpressionException(sprintf('Unexpected character "%s".', addcslashes($character, "\0..\37")), $source, new Span($offset, 1));
	}

}
