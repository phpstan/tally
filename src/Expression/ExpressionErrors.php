<?php declare(strict_types = 1);

namespace Tally\Expression;

use Tally\Text\ClosestName;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function get_debug_type;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use function var_export;

/**
 * The mistakes the type checker and the evaluator report, so both word them the same. Each
 * quotes the part of the expression that is wrong as the author wrote it.
 */
final class ExpressionErrors
{

	/** How many keys of an array a message about a missing key lists */
	private const KEYS_SHOWN_LIMIT = 5;

	public function __construct(private ClosestName $closestName)
	{
	}

	/**
	 * @param list<string> $known
	 */
	public function unknownVariable(string $source, Span $span, string $name, array $known): ExpressionException
	{
		return new ExpressionException(
			sprintf('Unknown variable "%s". %s', $name, $this->suggestion($name, $known, 'The variables are %s.', 'There are no variables.')),
			$source,
			$span,
		);
	}

	/**
	 * @param list<string> $known
	 */
	public function unknownFunction(string $source, Span $span, string $name, array $known): ExpressionException
	{
		return new ExpressionException(
			sprintf('Unknown function "%s". %s', $name, $this->suggestion($name, $known, 'The functions are %s.', 'There are no functions.')),
			$source,
			$span,
		);
	}

	public function argumentCount(string $source, Span $span, string $function, int $given): ExpressionException
	{
		return new ExpressionException(sprintf('%s() takes 1 argument, %d given.', $function, $given), $source, $span);
	}

	/**
	 * @param string $description what the value is, such as "an array of 3"
	 */
	public function notANumber(string $source, Span $span, string $description): ExpressionException
	{
		return new ExpressionException(sprintf('%s is %s, not a number.', $span->text($source), $description), $source, $span);
	}

	public function notAnArray(string $source, Span $span, string $description): ExpressionException
	{
		return new ExpressionException(sprintf('%s is %s, not an array.', $span->text($source), $description), $source, $span);
	}

	/**
	 * @param array<mixed> $array
	 */
	public function missingKey(string $source, Span $arraySpan, Span $keySpan, int|string $key, array $array): ExpressionException
	{
		$keys = array_keys($array);
		$stringKeys = array_values(array_filter($keys, is_string(...)));
		$closest = is_string($key) ? $this->closestName->find($key, $stringKeys) : null;
		if ($closest !== null) {
			$hint = sprintf('Did you mean "%s"?', $closest);
		} elseif ($keys === []) {
			$hint = 'It is empty.';
		} elseif (count($keys) > self::KEYS_SHOWN_LIMIT) {
			$hint = sprintf('It has %d keys.', count($keys));
		} else {
			$hint = sprintf('Its keys are %s.', implode(', ', array_map($this->key(...), $keys)));
		}

		return new ExpressionException(sprintf('%s has no key %s. %s', $arraySpan->text($source), $this->key($key), $hint), $source, $keySpan);
	}

	public function divisionByZero(string $source, Span $divisor): ExpressionException
	{
		return new ExpressionException(sprintf('Division by zero, %s is 0.', $divisor->text($source)), $source, $divisor);
	}

	public function zeroToNegativePower(string $source, Span $base): ExpressionException
	{
		return new ExpressionException(sprintf('Division by zero, %s is 0 and the exponent is negative.', $base->text($source)), $source, $base);
	}

	public function outOfDomain(string $source, Span $argument, string $function, string $needs, float $value): ExpressionException
	{
		return new ExpressionException(
			sprintf('%s() needs %s, %s is %s.', $function, $needs, $argument->text($source), $this->describe($value)),
			$source,
			$argument,
		);
	}

	/** A value for a message, such as 3, 0.5, "an array of 3", or "a string". A whole float is written like an int. */
	public function describe(mixed $value): string
	{
		return match (true) {
			is_int($value), is_float($value) => (string) $value,
			is_array($value) => sprintf('an array of %d', count($value)),
			is_string($value) => 'a string',
			is_bool($value) => var_export($value, true),
			$value === null => 'null',
			default => sprintf('an instance of %s', get_debug_type($value)),
		};
	}

	/**
	 * @param list<string> $known
	 */
	private function suggestion(string $name, array $known, string $list, string $none): string
	{
		$closest = $this->closestName->find($name, $known);
		if ($closest !== null) {
			return sprintf('Did you mean "%s"?', $closest);
		}
		if ($known === []) {
			return $none;
		}

		return sprintf($list, implode(', ', array_map(static fn (string $name): string => '"' . $name . '"', $known)));
	}

	private function key(int|string $key): string
	{
		return is_int($key) ? (string) $key : '"' . $key . '"';
	}

}
