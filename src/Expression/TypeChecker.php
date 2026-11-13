<?php declare(strict_types = 1);

namespace Tally\Expression;

use function array_keys;
use function count;

/**
 * Finds the mistakes in an expression that the types of its variables reveal, before anything
 * evaluates it: unknown variables and functions, arithmetic on an array, a key of a number.
 * What the types do not tell, like whether an array has a key, the evaluator reports.
 */
final class TypeChecker
{

	public function __construct(private FunctionLibrary $functions, private ExpressionErrors $errors)
	{
	}

	/**
	 * @param array<string, ValueType> $variables
	 * @throws ExpressionException
	 */
	public function check(Expression $expression, array $variables): ValueType
	{
		return $this->type($expression->source, $expression->node, $variables);
	}

	/**
	 * @param array<string, ValueType> $variables
	 * @throws ExpressionException
	 */
	private function type(string $source, Node $node, array $variables): ValueType
	{
		if ($node instanceof NumberNode) {
			return ValueType::Number;
		}
		if ($node instanceof VariableNode) {
			return $variables[$node->name] ?? throw $this->errors->unknownVariable($source, $node->span(), $node->name, array_keys($variables));
		}
		if ($node instanceof KeyNode) {
			$array = $this->type($source, $node->array, $variables);
			if (!$array->mayBe(ValueType::Array)) {
				throw $this->errors->notAnArray($source, $node->array->span(), $array->value);
			}

			return ValueType::Mixed;
		}
		if ($node instanceof CallNode) {
			if ($this->functions->find($node->name) === null) {
				throw $this->errors->unknownFunction($source, $node->nameSpan, $node->name, $this->functions->names());
			}
			if (count($node->arguments) !== 1) {
				throw $this->errors->argumentCount($source, $node->span(), $node->name, count($node->arguments));
			}

			$this->number($source, $node->arguments[0], $variables);

			return ValueType::Number;
		}
		if ($node instanceof NegateNode) {
			$this->number($source, $node->operand, $variables);

			return ValueType::Number;
		}

		$this->number($source, $node->left, $variables);
		$this->number($source, $node->right, $variables);

		return ValueType::Number;
	}

	/**
	 * @param array<string, ValueType> $variables
	 * @throws ExpressionException
	 */
	private function number(string $source, Node $node, array $variables): void
	{
		$type = $this->type($source, $node, $variables);
		if (!$type->mayBe(ValueType::Number)) {
			throw $this->errors->notANumber($source, $node->span(), $type->value);
		}
	}

}
