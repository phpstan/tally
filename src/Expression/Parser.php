<?php declare(strict_types = 1);

namespace Tally\Expression;

use function sprintf;
use function str_contains;
use function substr;

/**
 * Parses an expression by recursive descent. From the loosest to the tightest binding: + and -,
 * then * and /, then negation, then ^ (right-associative, so 2 ^ 2 ^ n is 2 ^ (2 ^ n), and
 * -n ^ 2 is -(n ^ 2)), then keys (input.items, rows[0], input['a b']), calls, numbers,
 * variables, and parentheses.
 *
 * Each step takes the position of its first token and returns the node, the span of the node
 * with the parentheses around it, which the span of an enclosing node starts or ends at, and
 * the position after it.
 */
final class Parser
{

	private const VALUE = 'a number, a name, or "("';

	private const ADDITIVE = [TokenType::Plus->name => Operator::Add, TokenType::Minus->name => Operator::Subtract];

	private const MULTIPLICATIVE = [TokenType::Star->name => Operator::Multiply, TokenType::Slash->name => Operator::Divide];

	public function __construct(private Lexer $lexer)
	{
	}

	/**
	 * @throws ExpressionException
	 */
	public function parse(string $source): Expression
	{
		$tokens = $this->lexer->tokenize($source);
		$first = $tokens->at(0);
		if ($first->type === TokenType::End) {
			throw new ExpressionException('The expression is empty.', $source, $first->span());
		}

		[$node, , $at] = $this->binary($source, $tokens, 0, self::ADDITIVE);
		$token = $tokens->at($at);
		if ($token->type === TokenType::RightParen) {
			throw new ExpressionException('Found ")" without a "(" before it.', $source, $token->span());
		}
		if ($token->type !== TokenType::End) {
			throw new ExpressionException(sprintf('Expected an operator, found "%s".', $token->text), $source, $token->span());
		}

		return new Expression($source, $node);
	}

	/**
	 * A chain of left-associative operations: + and - of products, or * and / of negations.
	 *
	 * @param array<string, Operator> $operators by the name of their token type
	 * @return array{Node, Span, int}
	 * @throws ExpressionException
	 */
	private function binary(string $source, TokenStream $tokens, int $at, array $operators): array
	{
		[$left, $leftSpan, $at] = $operators === self::ADDITIVE
			? $this->binary($source, $tokens, $at, self::MULTIPLICATIVE)
			: $this->unary($source, $tokens, $at);
		while (($operator = $operators[$tokens->at($at)->type->name] ?? null) !== null) {
			[$right, $rightSpan, $at] = $operators === self::ADDITIVE
				? $this->binary($source, $tokens, $at + 1, self::MULTIPLICATIVE)
				: $this->unary($source, $tokens, $at + 1);
			$leftSpan = $leftSpan->to($rightSpan);
			$left = new BinaryNode($operator, $left, $right, $leftSpan);
		}

		return [$left, $leftSpan, $at];
	}

	/**
	 * @return array{Node, Span, int}
	 * @throws ExpressionException
	 */
	private function unary(string $source, TokenStream $tokens, int $at): array
	{
		$minus = $tokens->at($at);
		if ($minus->type !== TokenType::Minus) {
			return $this->power($source, $tokens, $at);
		}

		[$operand, $operandSpan, $at] = $this->unary($source, $tokens, $at + 1);
		$span = $minus->span()->to($operandSpan);

		return [new NegateNode($operand, $span), $span, $at];
	}

	/**
	 * @return array{Node, Span, int}
	 * @throws ExpressionException
	 */
	private function power(string $source, TokenStream $tokens, int $at): array
	{
		[$base, $baseSpan, $at] = $this->postfix($source, $tokens, $at);
		if ($tokens->at($at)->type !== TokenType::Caret) {
			return [$base, $baseSpan, $at];
		}

		[$exponent, $exponentSpan, $at] = $this->unary($source, $tokens, $at + 1);
		$span = $baseSpan->to($exponentSpan);

		return [new BinaryNode(Operator::Power, $base, $exponent, $span), $span, $at];
	}

	/**
	 * @return array{Node, Span, int}
	 * @throws ExpressionException
	 */
	private function postfix(string $source, TokenStream $tokens, int $at): array
	{
		[$node, $span, $at] = $this->primary($source, $tokens, $at);
		while (true) {
			if ($tokens->at($at)->type === TokenType::Dot) {
				$key = $this->expect($source, $tokens->at($at + 1), [TokenType::Name], 'a key name after "."');
				$keySpan = $key->span();
				$value = $key->text;
				$at += 2;
			} elseif ($tokens->at($at)->type === TokenType::LeftBracket) {
				$key = $this->expect($source, $tokens->at($at + 1), [TokenType::Number, TokenType::String], 'a whole number or a quoted string as the key');
				if (str_contains($key->text, '.')) {
					throw $this->unexpected($source, $key, 'a whole number or a quoted string as the key');
				}

				$keySpan = $key->span();
				$value = $key->type === TokenType::Number ? (int) $key->text : substr($key->text, 1, -1);
				$this->expect($source, $tokens->at($at + 2), [TokenType::RightBracket], '"]" after the key');
				$at += 3;
			} else {
				return [$node, $span, $at];
			}

			$span = $span->to($tokens->at($at - 1)->span());
			$node = new KeyNode($node, $value, $keySpan, $span);
		}
	}

	/**
	 * @return array{Node, Span, int}
	 * @throws ExpressionException
	 */
	private function primary(string $source, TokenStream $tokens, int $at): array
	{
		$token = $this->expect($source, $tokens->at($at), [TokenType::Number, TokenType::Name, TokenType::LeftParen], self::VALUE);
		if ($token->type === TokenType::Number) {
			$value = str_contains($token->text, '.') ? (float) $token->text : (int) $token->text;

			return [new NumberNode($value, $token->span()), $token->span(), $at + 1];
		}
		if ($token->type === TokenType::LeftParen) {
			[$node, , $at] = $this->binary($source, $tokens, $at + 1, self::ADDITIVE);
			$close = $this->expect($source, $tokens->at($at), [TokenType::RightParen], '")" to close the "("');

			return [$node, $token->span()->to($close->span()), $at + 1];
		}
		if ($tokens->at($at + 1)->type !== TokenType::LeftParen) {
			return [new VariableNode($token->text, $token->span()), $token->span(), $at + 1];
		}

		return $this->call($source, $tokens, $at);
	}

	/**
	 * @return array{Node, Span, int}
	 * @throws ExpressionException
	 */
	private function call(string $source, TokenStream $tokens, int $at): array
	{
		$name = $tokens->at($at);
		$arguments = [];
		$at += 2;
		if ($tokens->at($at)->type !== TokenType::RightParen) {
			while (true) {
				[$arguments[], , $at] = $this->binary($source, $tokens, $at, self::ADDITIVE);
				$separator = $this->expect($source, $tokens->at($at), [TokenType::Comma, TokenType::RightParen], '"," or ")" after the argument');
				if ($separator->type === TokenType::RightParen) {
					break;
				}

				$at++;
			}
		}

		$span = $name->span()->to($tokens->at($at)->span());

		return [new CallNode($name->text, $arguments, $name->span(), $span), $span, $at + 1];
	}

	/**
	 * @param list<TokenType> $types
	 * @throws ExpressionException
	 */
	private function expect(string $source, Token $token, array $types, string $expected): Token
	{
		foreach ($types as $type) {
			if ($token->type === $type) {
				return $token;
			}
		}

		throw $this->unexpected($source, $token, $expected);
	}

	private function unexpected(string $source, Token $token, string $expected): ExpressionException
	{
		if ($token->type === TokenType::End) {
			return new ExpressionException(sprintf('Expected %s, but the expression ends.', $expected), $source, $token->span());
		}

		return new ExpressionException(sprintf('Expected %s, found "%s".', $expected, $token->text), $source, $token->span());
	}

}
