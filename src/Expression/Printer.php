<?php declare(strict_types = 1);

namespace Tally\Expression;

use function array_map;
use function implode;
use function is_int;
use function preg_match;
use function sprintf;
use function str_contains;
use function var_export;

/**
 * Writes an expression in its canonical form, with only the parentheses the tree needs: parsing
 * what it prints gives the same tree.
 */
final class Printer
{

	private const ADDITIVE = 1;

	private const MULTIPLICATIVE = 2;

	private const NEGATION = 3;

	private const POWER = 4;

	/** Numbers, variables, keys, and calls */
	private const ATOM = 5;

	public function print(Node $node): string
	{
		if ($node instanceof NumberNode) {
			return is_int($node->value) ? (string) $node->value : var_export($node->value, true);
		}
		if ($node instanceof VariableNode) {
			return $node->name;
		}
		if ($node instanceof CallNode) {
			return sprintf('%s(%s)', $node->name, implode(', ', array_map($this->print(...), $node->arguments)));
		}
		if ($node instanceof KeyNode) {
			return $this->operand($node->array, self::ATOM) . $this->key($node->key);
		}
		if ($node instanceof NegateNode) {
			return '-' . $this->operand($node->operand, self::NEGATION);
		}
		if ($node->operator === Operator::Power) {
			// the base is a key or tighter, the exponent a negation or tighter
			return $this->operand($node->left, self::ATOM) . '^' . $this->operand($node->right, self::NEGATION);
		}

		$precedence = $this->precedence($node);

		// left-associative: an operation of the same precedence on the right needs parentheses
		return sprintf('%s %s %s', $this->operand($node->left, $precedence), $node->operator->value, $this->operand($node->right, $precedence + 1));
	}

	/**
	 * @param int $precedence the loosest a node may bind without parentheses
	 */
	private function operand(Node $node, int $precedence): string
	{
		$printed = $this->print($node);

		return $this->precedence($node) < $precedence ? '(' . $printed . ')' : $printed;
	}

	private function precedence(Node $node): int
	{
		if ($node instanceof NegateNode) {
			return self::NEGATION;
		}
		if (!$node instanceof BinaryNode) {
			return self::ATOM;
		}

		return match ($node->operator) {
			Operator::Add, Operator::Subtract => self::ADDITIVE,
			Operator::Multiply, Operator::Divide => self::MULTIPLICATIVE,
			Operator::Power => self::POWER,
		};
	}

	private function key(int|string $key): string
	{
		if (is_int($key)) {
			return '[' . $key . ']';
		}
		if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) === 1) {
			return '.' . $key;
		}

		return str_contains($key, '\'') ? '["' . $key . '"]' : '[\'' . $key . '\']';
	}

}
