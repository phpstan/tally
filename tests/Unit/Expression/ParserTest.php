<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Expression;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Expression\ExpressionException;
use Tally\Expression\Lexer;
use Tally\Expression\Parser;
use function implode;

final class ParserTest extends TestCase
{

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function dataParse(): iterable
	{
		yield 'a whole number' => ['1', '1'];
		yield 'a decimal number' => ['0.5', '0.5'];
		yield 'a decimal number with a whole value' => ['2.0', '2.0'];
		yield 'a variable' => ['n', 'n'];
		yield 'addition' => ['a + b', '(+ a b)'];
		yield 'subtraction' => ['a - b', '(- a b)'];
		yield 'multiplication' => ['a * b', '(* a b)'];
		yield 'division' => ['a / b', '(/ a b)'];
		yield 'power' => ['a ^ b', '(^ a b)'];
		yield 'addition is left-associative' => ['a - b + c', '(+ (- a b) c)'];
		yield 'multiplication is left-associative' => ['a / b * c', '(* (/ a b) c)'];
		yield 'power is right-associative' => ['2 ^ 2 ^ n', '(^ 2 (^ 2 n))'];
		yield 'multiplication binds tighter than addition' => ['a + b * c - d', '(- (+ a (* b c)) d)'];
		yield 'power binds tighter than multiplication' => ['a * b ^ c', '(* a (^ b c))'];
		yield 'power on the left of multiplication' => ['a ^ b * c', '(* (^ a b) c)'];
		yield 'parentheses' => ['(a + b) * c', '(* (+ a b) c)'];
		yield 'nested parentheses' => ['((a))', 'a'];
		yield 'negation' => ['-n', '(neg n)'];
		yield 'double negation' => ['--n', '(neg (neg n))'];
		yield 'negation binds looser than power' => ['-n ^ 2', '(neg (^ n 2))'];
		yield 'negation binds tighter than multiplication' => ['-a * b', '(* (neg a) b)'];
		yield 'a negative exponent' => ['n ^ -1', '(^ n (neg 1))'];
		yield 'a negated power as an exponent' => ['2 ^ -n ^ 2', '(^ 2 (neg (^ n 2)))'];
		yield 'a negated operand of multiplication' => ['a * -b', '(* a (neg b))'];
		yield 'a call' => ['log(n)', '(log n)'];
		yield 'a call without arguments' => ['f()', '(f)'];
		yield 'a call with arguments' => ['f(a, b + 1, c)', '(f a (+ b 1) c)'];
		yield 'nested calls' => ['log(log(n))', '(log (log n))'];
		yield 'a key by name' => ['input.items', "(key input 'items')"];
		yield 'a key by number' => ['rows[0]', '(key rows 0)'];
		yield 'a key by string' => ["input['a b']", "(key input 'a b')"];
		yield 'a key by double-quoted string' => ['input["a"]', "(key input 'a')"];
		yield 'a numeric string key stays a string' => ["input['0']", "(key input '0')"];
		yield 'a path' => ["input.rows[2].cells['x']", "(key (key (key (key input 'rows') 2) 'cells') 'x')"];
		yield 'a key of a call' => ['f(n).x', "(key (f n) 'x')"];
		yield 'a key of parentheses' => ['(a).x', "(key a 'x')"];
		yield 'a key binds tighter than power' => ['n ^ a.b', "(^ n (key a 'b'))"];
		yield 'a key binds tighter than negation' => ['-a.b', "(neg (key a 'b'))"];
		yield 'a key of a number' => ['1.x', "(key 1 'x')"];
		yield 'every complexity of the table' => ['n * log(n) + n ^ 2 / log(log(n)) - 2 ^ n * factorial(n)', '(- (+ (* n (log n)) (/ (^ n 2) (log (log n)))) (* (^ 2 n) (factorial n)))'];
	}

	/**
	 * @throws ExpressionException
	 */
	#[DataProvider('dataParse')]
	public function testParse(string $source, string $tree): void
	{
		$expression = (new Parser(new Lexer()))->parse($source);
		$this->assertSame($source, $expression->source);
		$this->assertSame($tree, (new AstDump())->tree($expression->node));
	}

	/**
	 * @return iterable<string, array{string, list<string>}>
	 */
	public static function dataSpans(): iterable
	{
		yield 'a binary operation spans its operands' => ['a  +  b', ['a  +  b', 'a', 'b']];
		yield 'parentheses belong to the operation they are in' => ['(a + b) * (c)', ['(a + b) * (c)', 'a + b', 'a', 'b', 'c']];
		yield 'negation spans the minus' => ['- (n)', ['- (n)', 'n']];
		yield 'a call spans its name and parentheses' => ['log( n , 2 )', ['log( n , 2 )', 'name log', 'n', '2']];
		yield 'a key by name' => ['input . items', ['input . items', 'input', 'key items']];
		yield 'a key by string spans the brackets' => ["(input)[ 'a' ]", ["(input)[ 'a' ]", 'input', "key 'a'"]];
		yield 'a path' => ['a.b[0]', ['a.b[0]', 'a.b', 'a', 'key b', 'key 0']];
		yield 'a power with a negative exponent' => ['n^-(1)', ['n^-(1)', 'n', '-(1)', '1']];
	}

	/**
	 * @param list<string> $spans
	 * @throws ExpressionException
	 */
	#[DataProvider('dataSpans')]
	public function testSpans(string $source, array $spans): void
	{
		$expression = (new Parser(new Lexer()))->parse($source);
		$this->assertSame($spans, (new AstDump())->spans($expression->node, $source));
	}

	/**
	 * @return iterable<string, array{string, list<string>}>
	 */
	public static function dataInvalid(): iterable
	{
		yield 'empty' => ['', [
			'The expression is empty.',
			'  ',
			'  ^',
		]];
		yield 'only spaces' => ['   ', [
			'The expression is empty.',
			'     ',
			'     ^',
		]];
		yield 'two values without an operator' => ['n log(n)', [
			'Expected an operator, found "log".',
			'  n log(n)',
			'    ^^^',
		]];
		yield 'a closing parenthesis without an opening one' => ['n + 1)', [
			'Found ")" without a "(" before it.',
			'  n + 1)',
			'       ^',
		]];
		yield 'a missing operand' => ['n *', [
			'Expected a number, a name, or "(", but the expression ends.',
			'  n *',
			'     ^',
		]];
		yield 'an operator in place of an operand' => ['n * / 2', [
			'Expected a number, a name, or "(", found "/".',
			'  n * / 2',
			'      ^',
		]];
		yield 'an unclosed parenthesis' => ['(n + 1', [
			'Expected ")" to close the "(", but the expression ends.',
			'  (n + 1',
			'        ^',
		]];
		yield 'something else in place of a closing parenthesis' => ['(n + 1]', [
			'Expected ")" to close the "(", found "]".',
			'  (n + 1]',
			'        ^',
		]];
		yield 'an unclosed call' => ['log(n', [
			'Expected "," or ")" after the argument, but the expression ends.',
			'  log(n',
			'       ^',
		]];
		yield 'arguments without a comma' => ['f(a b)', [
			'Expected "," or ")" after the argument, found "b".',
			'  f(a b)',
			'      ^',
		]];
		yield 'a trailing comma' => ['f(a,)', [
			'Expected a number, a name, or "(", found ")".',
			'  f(a,)',
			'      ^',
		]];
		yield 'no key name after a dot' => ['input.', [
			'Expected a key name after ".", but the expression ends.',
			'  input.',
			'        ^',
		]];
		yield 'a number after a dot' => ['input.0', [
			'Expected a key name after ".", found "0".',
			'  input.0',
			'        ^',
		]];
		yield 'a variable as a key' => ['input[n]', [
			'Expected a whole number or a quoted string as the key, found "n".',
			'  input[n]',
			'        ^',
		]];
		yield 'a decimal number as a key' => ['input[1.5]', [
			'Expected a whole number or a quoted string as the key, found "1.5".',
			'  input[1.5]',
			'        ^^^',
		]];
		yield 'an unclosed key' => ["input['a'", [
			'Expected "]" after the key, but the expression ends.',
			"  input['a'",
			'           ^',
		]];
		yield 'a string as a value' => ["'n'", [
			'Expected a number, a name, or "(", found "\'n\'".',
			"  'n'",
			'  ^^^',
		]];
		yield 'a lexer error' => ['n % 2', [
			'Unexpected character "%".',
			'  n % 2',
			'    ^',
		]];
	}

	/**
	 * @param list<string> $message
	 */
	#[DataProvider('dataInvalid')]
	public function testInvalid(string $source, array $message): void
	{
		try {
			(new Parser(new Lexer()))->parse($source);
			self::fail('No exception.');
		} catch (ExpressionException $e) {
			$this->assertSame(implode("\n", $message), $e->getMessage());
		}
	}

}
