<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Expression;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Expression\ExpressionErrors;
use Tally\Expression\ExpressionException;
use Tally\Expression\FunctionLibrary;
use Tally\Expression\Functions\Exponential;
use Tally\Expression\Functions\Factorial;
use Tally\Expression\Functions\Log;
use Tally\Expression\Functions\SquareRoot;
use Tally\Expression\Lexer;
use Tally\Expression\Parser;
use Tally\Expression\TypeChecker;
use Tally\Expression\ValueType;
use Tally\Text\ClosestName;
use function implode;

final class TypeCheckerTest extends TestCase
{

	/**
	 * @return iterable<string, array{string, array<string, ValueType>, ValueType}>
	 */
	public static function dataCheck(): iterable
	{
		yield 'a number' => ['1', [], ValueType::Number];
		yield 'a number variable' => ['n', ['n' => ValueType::Number], ValueType::Number];
		yield 'an array variable' => ['input', ['input' => ValueType::Array], ValueType::Array];
		yield 'a string variable' => ['s', ['s' => ValueType::String], ValueType::String];
		yield 'a key of an array is anything' => ['input.items', ['input' => ValueType::Array], ValueType::Mixed];
		yield 'a key of anything' => ['input.items[0]', ['input' => ValueType::Mixed], ValueType::Mixed];
		yield 'arithmetic' => ['n * log(n) + n ^ 2 / sqrt(n) - -exp(n)', ['n' => ValueType::Number], ValueType::Number];
		yield 'arithmetic on anything' => ['input.a * input.b', ['input' => ValueType::Array], ValueType::Number];
		yield 'a call on anything' => ['factorial(input)', ['input' => ValueType::Mixed], ValueType::Number];
		yield 'negating anything' => ['-input', ['input' => ValueType::Mixed], ValueType::Number];
	}

	/**
	 * @param array<string, ValueType> $variables
	 * @throws ExpressionException
	 */
	#[DataProvider('dataCheck')]
	public function testCheck(string $source, array $variables, ValueType $type): void
	{
		$this->assertSame($type, $this->checker()->check((new Parser(new Lexer()))->parse($source), $variables));
	}

	/**
	 * @return iterable<string, array{string, array<string, ValueType>, list<string>}>
	 */
	public static function dataInvalid(): iterable
	{
		yield 'an unknown variable' => ['n * log(m)', ['n' => ValueType::Number], [
			'Unknown variable "m". Did you mean "n"?',
			'  n * log(m)',
			'          ^',
		]];
		yield 'an unknown variable among several' => ['size', ['numbers' => ValueType::Array, 'seed' => ValueType::Number], [
			'Unknown variable "size". The variables are "numbers", "seed".',
			'  size',
			'  ^^^^',
		]];
		yield 'an unknown function' => ['n * lgo(n)', ['n' => ValueType::Number], [
			'Unknown function "lgo". Did you mean "log"?',
			'  n * lgo(n)',
			'      ^^^',
		]];
		yield 'an unknown function with an unknown argument' => ['lg(m)', [], [
			'Unknown function "lg". Did you mean "log"?',
			'  lg(m)',
			'  ^^',
		]];
		yield 'too many arguments' => ['log(n, 2)', ['n' => ValueType::Number], [
			'log() takes 1 argument, 2 given.',
			'  log(n, 2)',
			'  ^^^^^^^^^',
		]];
		yield 'an argument that is not a number' => ['log(input)', ['input' => ValueType::Array], [
			'input is an array, not a number.',
			'  log(input)',
			'      ^^^^^',
		]];
		yield 'an unknown variable as an argument' => ['log(m)', ['n' => ValueType::Number], [
			'Unknown variable "m". Did you mean "n"?',
			'  log(m)',
			'      ^',
		]];
		yield 'arithmetic on an array' => ['input * 2', ['input' => ValueType::Array], [
			'input is an array, not a number.',
			'  input * 2',
			'  ^^^^^',
		]];
		yield 'arithmetic on a string on the right' => ['2 + s', ['s' => ValueType::String], [
			's is a string, not a number.',
			'  2 + s',
			'      ^',
		]];
		yield 'negating a bool' => ['-b', ['b' => ValueType::Bool], [
			'b is a bool, not a number.',
			'  -b',
			'   ^',
		]];
		yield 'a key of a number' => ['n.items', ['n' => ValueType::Number], [
			'n is a number, not an array.',
			'  n.items',
			'  ^',
		]];
		yield 'a key of an object' => ['o.items', ['o' => ValueType::Object], [
			'o is an object, not an array.',
			'  o.items',
			'  ^',
		]];
		yield 'a key of null' => ['v[0]', ['v' => ValueType::Null], [
			'v is null, not an array.',
			'  v[0]',
			'  ^',
		]];
		yield 'a key of arithmetic' => ['(n + 1).x', ['n' => ValueType::Number], [
			'n + 1 is a number, not an array.',
			'  (n + 1).x',
			'   ^^^^^',
		]];
		yield 'a key of a call' => ['log(n).x', ['n' => ValueType::Number], [
			'log(n) is a number, not an array.',
			'  log(n).x',
			'  ^^^^^^',
		]];
		yield 'a key of a negation' => ['(-n).x', ['n' => ValueType::Number], [
			'-n is a number, not an array.',
			'  (-n).x',
			'   ^^',
		]];
		yield 'a key of a number literal' => ['1.x', [], [
			'1 is a number, not an array.',
			'  1.x',
			'  ^',
		]];
	}

	/**
	 * @param array<string, ValueType> $variables
	 * @param list<string> $message
	 */
	#[DataProvider('dataInvalid')]
	public function testInvalid(string $source, array $variables, array $message): void
	{
		try {
			$this->checker()->check((new Parser(new Lexer()))->parse($source), $variables);
			self::fail('No exception.');
		} catch (ExpressionException $e) {
			$this->assertSame(implode("\n", $message), $e->getMessage());
		}
	}

	private function checker(): TypeChecker
	{
		return new TypeChecker(
			new FunctionLibrary([new Log(), new SquareRoot(), new Exponential(), new Factorial()]),
			new ExpressionErrors(new ClosestName()),
		);
	}

}
