<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Expression;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tally\Expression\Evaluator;
use Tally\Expression\ExpressionErrors;
use Tally\Expression\ExpressionException;
use Tally\Expression\FunctionLibrary;
use Tally\Expression\Functions\Exponential;
use Tally\Expression\Functions\Factorial;
use Tally\Expression\Functions\Log;
use Tally\Expression\Functions\SquareRoot;
use Tally\Expression\Lexer;
use Tally\Expression\Parser;
use Tally\Text\ClosestName;
use function implode;
use function log;
use const INF;

final class EvaluatorTest extends TestCase
{

	/**
	 * @return iterable<string, array{string, array<string, mixed>, mixed}>
	 */
	public static function dataEvaluate(): iterable
	{
		$input = ['items' => [1, 2, 3], 'a b' => 'x', 0 => 'zero', 'rows' => [['cells' => [7]]]];
		yield 'a whole number stays whole' => ['12', [], 12];
		yield 'a decimal number' => ['0.5', [], 0.5];
		yield 'a variable' => ['n', ['n' => 4], 4];
		yield 'a variable holding an array' => ['input', ['input' => [1]], [1]];
		yield 'a variable holding null' => ['input', ['input' => null], null];
		yield 'a key by name' => ['input.items', ['input' => $input], [1, 2, 3]];
		yield 'a key by number' => ['input.items[1]', ['input' => $input], 2];
		yield 'a key by string' => ["input['a b']", ['input' => $input], 'x'];
		yield 'a numeric string key finds the number key' => ["input['0']", ['input' => $input], 'zero'];
		yield 'a key holding null' => ['input.x', ['input' => ['x' => null]], null];
		yield 'a long path' => ['input.rows[0].cells[0]', ['input' => $input], 7];
		yield 'addition is a float' => ['n + 1', ['n' => 2], 3.0];
		yield 'subtraction' => ['n - 5', ['n' => 2], -3.0];
		yield 'multiplication' => ['n * 3', ['n' => 2], 6.0];
		yield 'division' => ['n / 4', ['n' => 2], 0.5];
		yield 'power' => ['n ^ 3', ['n' => 2], 8.0];
		yield 'power is right-associative' => ['2 ^ 3 ^ 2', [], 512.0];
		yield 'a negative exponent' => ['n ^ -1', ['n' => 4], 0.25];
		yield 'a power of 0' => ['0 ^ n', ['n' => 2], 0.0];
		yield 'zero to the power of zero' => ['n ^ 0', ['n' => 0], 1.0];
		yield 'negation' => ['-n', ['n' => 2], -2.0];
		yield 'a float variable' => ['n * 2', ['n' => 1.5], 3.0];
		yield 'a number from a path' => ['input.items[2] * 2', ['input' => $input], 6.0];
		yield 'precedence' => ['1 + 2 * 3 ^ 2 - 4 / 2', [], 17.0];
		yield 'log' => ['log(n)', ['n' => 1], 0.0];
		yield 'log of e' => ['log(exp(1))', [], 1.0];
		yield 'n log n' => ['n * log(n)', ['n' => 8], 8 * log(8)];
		yield 'sqrt' => ['sqrt(n)', ['n' => 16], 4.0];
		yield 'factorial' => ['factorial(n)', ['n' => 5], 120.0];
		yield 'an overflow is infinite' => ['2 ^ n', ['n' => 2000], INF];
	}

	/**
	 * @param array<string, mixed> $variables
	 * @throws ExpressionException
	 */
	#[DataProvider('dataEvaluate')]
	public function testEvaluate(string $source, array $variables, mixed $value): void
	{
		$this->assertSame($value, $this->evaluator()->evaluate((new Parser(new Lexer()))->parse($source), $variables));
	}

	/**
	 * @return iterable<string, array{string, array<string, mixed>, float}>
	 */
	public static function dataEvaluateNumber(): iterable
	{
		yield 'a whole number is a float' => ['1', [], 1.0];
		yield 'a whole number from a variable' => ['n', ['n' => 3], 3.0];
		yield 'arithmetic' => ['n * log(n)', ['n' => 1], 0.0];
	}

	/**
	 * @param array<string, mixed> $variables
	 * @throws ExpressionException
	 */
	#[DataProvider('dataEvaluateNumber')]
	public function testEvaluateNumber(string $source, array $variables, float $value): void
	{
		$this->assertSame($value, $this->evaluator()->evaluateNumber((new Parser(new Lexer()))->parse($source), $variables));
	}

	public function testEvaluateNumberOfAnArray(): void
	{
		try {
			$this->evaluator()->evaluateNumber((new Parser(new Lexer()))->parse('input.items'), ['input' => ['items' => [1]]]);
			self::fail('No exception.');
		} catch (ExpressionException $e) {
			$this->assertSame(implode("\n", ['input.items is an array of 1, not a number.', '  input.items', '  ^^^^^^^^^^^']), $e->getMessage());
		}
	}

	/**
	 * @return iterable<string, array{string, array<string, mixed>, list<string>}>
	 */
	public static function dataInvalid(): iterable
	{
		$input = ['items' => [1, 2, 3], 'count' => 3];
		yield 'an unknown variable close to a known one' => ['nn * 2', ['n' => 1, 'input' => []], [
			'Unknown variable "nn". Did you mean "n"?',
			'  nn * 2',
			'  ^^',
		]];
		yield 'an unknown variable' => ['size', ['n' => 1, 'input' => []], [
			'Unknown variable "size". The variables are "n", "input".',
			'  size',
			'  ^^^^',
		]];
		yield 'no variables' => ['n', [], [
			'Unknown variable "n". There are no variables.',
			'  n',
			'  ^',
		]];
		yield 'a key close to a known one' => ['input.itms', ['input' => $input], [
			'input has no key "itms". Did you mean "items"?',
			'  input.itms',
			'        ^^^^',
		]];
		yield 'a key that is not there' => ['input.rows', ['input' => $input], [
			'input has no key "rows". Its keys are "items", "count".',
			'  input.rows',
			'        ^^^^',
		]];
		yield 'a number key that is not there' => ['input.items[3]', ['input' => $input], [
			'input.items has no key 3. Its keys are 0, 1, 2.',
			'  input.items[3]',
			'              ^',
		]];
		yield 'a number key close to a string key' => ['input[1]', ['input' => ['2' => 0, 'a1' => 0]], [
			'input has no key 1. Its keys are 2, "a1".',
			'  input[1]',
			'        ^',
		]];
		yield 'a key of an empty array' => ['input.items', ['input' => []], [
			'input has no key "items". It is empty.',
			'  input.items',
			'        ^^^^^',
		]];
		yield 'five keys are listed' => ['input[5]', ['input' => [1, 2, 3, 4, 5]], [
			'input has no key 5. Its keys are 0, 1, 2, 3, 4.',
			'  input[5]',
			'        ^',
		]];
		yield 'six keys are counted' => ['input[6]', ['input' => [1, 2, 3, 4, 5, 6]], [
			'input has no key 6. It has 6 keys.',
			'  input[6]',
			'        ^',
		]];
		yield 'a key of a number' => ['input.count.x', ['input' => $input], [
			'input.count is 3, not an array.',
			'  input.count.x',
			'  ^^^^^^^^^^^',
		]];
		yield 'a key of a float' => ['n.x', ['n' => 1.5], [
			'n is 1.5, not an array.',
			'  n.x',
			'  ^',
		]];
		yield 'a key of a string' => ['s[0]', ['s' => 'abc'], [
			's is a string, not an array.',
			'  s[0]',
			'  ^',
		]];
		yield 'a key of null' => ['v.x', ['v' => null], [
			'v is null, not an array.',
			'  v.x',
			'  ^',
		]];
		yield 'a key of a bool' => ['v.x', ['v' => false], [
			'v is false, not an array.',
			'  v.x',
			'  ^',
		]];
		yield 'a key of an object' => ['v.x', ['v' => new stdClass()], [
			'v is an instance of stdClass, not an array.',
			'  v.x',
			'  ^',
		]];
		yield 'arithmetic on an array' => ['input.items * 2', ['input' => $input], [
			'input.items is an array of 3, not a number.',
			'  input.items * 2',
			'  ^^^^^^^^^^^',
		]];
		yield 'arithmetic on the right' => ['2 * input.items', ['input' => $input], [
			'input.items is an array of 3, not a number.',
			'  2 * input.items',
			'      ^^^^^^^^^^^',
		]];
		yield 'arithmetic on a numeric string' => ['n + 1', ['n' => '1'], [
			'n is a string, not a number.',
			'  n + 1',
			'  ^',
		]];
		yield 'negating an array' => ['-input', ['input' => []], [
			'input is an array of 0, not a number.',
			'  -input',
			'   ^^^^^',
		]];
		yield 'a function argument that is not a number' => ['log(input)', ['input' => [1]], [
			'input is an array of 1, not a number.',
			'  log(input)',
			'      ^^^^^',
		]];
		yield 'an unknown function close to a known one' => ['lg(n)', ['n' => 1], [
			'Unknown function "lg". Did you mean "log"?',
			'  lg(n)',
			'  ^^',
		]];
		yield 'an unknown function' => ['count(n)', ['n' => 1], [
			'Unknown function "count". The functions are "exp", "factorial", "log", "sqrt".',
			'  count(n)',
			'  ^^^^^',
		]];
		yield 'too many arguments' => ['log(n, 2)', ['n' => 1], [
			'log() takes 1 argument, 2 given.',
			'  log(n, 2)',
			'  ^^^^^^^^^',
		]];
		yield 'no arguments' => ['sqrt()', [], [
			'sqrt() takes 1 argument, 0 given.',
			'  sqrt()',
			'  ^^^^^^',
		]];
		yield 'log of 0' => ['log(n - 1)', ['n' => 1], [
			'log() needs a number above 0, n - 1 is 0.',
			'  log(n - 1)',
			'      ^^^^^',
		]];
		yield 'sqrt of a negative number' => ['sqrt(-n)', ['n' => 2], [
			'sqrt() needs a number from 0, -n is -2.',
			'  sqrt(-n)',
			'       ^^',
		]];
		yield 'factorial of a fraction' => ['factorial(n / 2)', ['n' => 3], [
			'factorial() needs a whole number from 0, n / 2 is 1.5.',
			'  factorial(n / 2)',
			'            ^^^^^',
		]];
		yield 'division by zero' => ['n / (n - 2)', ['n' => 2], [
			'Division by zero, n - 2 is 0.',
			'  n / (n - 2)',
			'       ^^^^^',
		]];
		yield 'zero to a negative power' => ['(n - 2) ^ -1', ['n' => 2], [
			'Division by zero, n - 2 is 0 and the exponent is negative.',
			'  (n - 2) ^ -1',
			'   ^^^^^',
		]];
	}

	/**
	 * @param array<string, mixed> $variables
	 * @param list<string> $message
	 */
	#[DataProvider('dataInvalid')]
	public function testInvalid(string $source, array $variables, array $message): void
	{
		try {
			$this->evaluator()->evaluate((new Parser(new Lexer()))->parse($source), $variables);
			self::fail('No exception.');
		} catch (ExpressionException $e) {
			$this->assertSame(implode("\n", $message), $e->getMessage());
		}
	}

	private function evaluator(): Evaluator
	{
		return new Evaluator(
			new FunctionLibrary([new Log(), new SquareRoot(), new Exponential(), new Factorial()]),
			new ExpressionErrors(new ClosestName()),
		);
	}

}
