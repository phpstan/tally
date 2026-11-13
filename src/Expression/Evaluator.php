<?php declare(strict_types = 1);

namespace Tally\Expression;

use function array_key_exists;
use function array_keys;
use function count;
use function is_array;
use function is_float;
use function is_int;

/**
 * Computes the value of an expression. A path, like input.items, gives the value it points to;
 * arithmetic and functions give a float.
 */
final class Evaluator
{

	public function __construct(private FunctionLibrary $functions, private ExpressionErrors $errors)
	{
	}

	/**
	 * @param array<string, mixed> $variables
	 * @throws ExpressionException
	 */
	public function evaluate(Expression $expression, array $variables): mixed
	{
		return $this->value($expression->source, $expression->node, $variables);
	}

	/**
	 * Like evaluate(), for an expression that must give a number, such as a formula
	 *
	 * @param array<string, mixed> $variables
	 * @throws ExpressionException
	 */
	public function evaluateNumber(Expression $expression, array $variables): float
	{
		return $this->number($expression->source, $expression->node, $variables);
	}

	/**
	 * @param array<string, mixed> $variables
	 * @throws ExpressionException
	 */
	private function value(string $source, Node $node, array $variables): mixed
	{
		if ($node instanceof NumberNode) {
			return $node->value;
		}
		if ($node instanceof VariableNode) {
			if (!array_key_exists($node->name, $variables)) {
				throw $this->errors->unknownVariable($source, $node->span(), $node->name, array_keys($variables));
			}

			return $variables[$node->name];
		}
		if ($node instanceof KeyNode) {
			$array = $this->value($source, $node->array, $variables);
			if (!is_array($array)) {
				throw $this->errors->notAnArray($source, $node->array->span(), $this->errors->describe($array));
			}
			if (!array_key_exists($node->key, $array)) {
				throw $this->errors->missingKey($source, $node->array->span(), $node->keySpan, $node->key, $array);
			}

			return $array[$node->key];
		}
		if ($node instanceof CallNode) {
			return $this->call($source, $node, $variables);
		}
		if ($node instanceof NegateNode) {
			return -$this->number($source, $node->operand, $variables);
		}

		$left = $this->number($source, $node->left, $variables);
		$right = $this->number($source, $node->right, $variables);

		return match ($node->operator) {
			Operator::Add => $left + $right,
			Operator::Subtract => $left - $right,
			Operator::Multiply => $left * $right,
			Operator::Divide => $right === 0.0 ? throw $this->errors->divisionByZero($source, $node->right->span()) : $left / $right,
			Operator::Power => $left === 0.0 && $right < 0 ? throw $this->errors->zeroToNegativePower($source, $node->left->span()) : $left ** $right,
		};
	}

	/**
	 * @param array<string, mixed> $variables
	 * @throws ExpressionException
	 */
	private function call(string $source, CallNode $node, array $variables): float
	{
		$function = $this->functions->find($node->name);
		if ($function === null) {
			throw $this->errors->unknownFunction($source, $node->nameSpan, $node->name, $this->functions->names());
		}
		if (count($node->arguments) !== 1) {
			throw $this->errors->argumentCount($source, $node->span(), $node->name, count($node->arguments));
		}

		$argument = $node->arguments[0];
		$x = $this->number($source, $argument, $variables);
		try {
			return $function->apply($x);
		} catch (OutOfDomainException $e) {
			throw $this->errors->outOfDomain($source, $argument->span(), $node->name, $e->needs, $x);
		}
	}

	/**
	 * @param array<string, mixed> $variables
	 * @throws ExpressionException
	 */
	private function number(string $source, Node $node, array $variables): float
	{
		$value = $this->value($source, $node, $variables);
		if (!is_int($value) && !is_float($value)) {
			throw $this->errors->notANumber($source, $node->span(), $this->errors->describe($value));
		}

		return (float) $value;
	}

}
