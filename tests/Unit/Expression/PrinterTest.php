<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Expression;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Expression\ExpressionException;
use Tally\Expression\Lexer;
use Tally\Expression\Parser;
use Tally\Expression\Printer;

final class PrinterTest extends TestCase
{

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function dataPrint(): iterable
	{
		yield 'a whole number' => ['12', '12'];
		yield 'a decimal number' => ['0.50', '0.5'];
		yield 'a decimal number with a whole value' => ['2.0', '2.0'];
		yield 'a variable' => ['n', 'n'];
		yield 'spaces around operators' => ['a+b-c*d/e', 'a + b - c * d / e'];
		yield 'no spaces around power' => ['n ^ 2', 'n^2'];
		yield 'parentheses that do nothing' => ['((a) + (b * c))', 'a + b * c'];
		yield 'a sum as the right operand of a difference' => ['a - (b + c)', 'a - (b + c)'];
		yield 'a sum as the right operand of a sum' => ['a + (b + c)', 'a + (b + c)'];
		yield 'a sum as the left operand of a sum' => ['(a + b) + c', 'a + b + c'];
		yield 'a sum as an operand of a product' => ['(a + b) * (c - d)', '(a + b) * (c - d)'];
		yield 'a product as the right operand of a quotient' => ['a / (b * c)', 'a / (b * c)'];
		yield 'a product as the left operand of a quotient' => ['(a * b) / c', 'a * b / c'];
		yield 'a product as an operand of a sum' => ['a * b + c * d', 'a * b + c * d'];
		yield 'a power as the right operand of a power' => ['2 ^ (2 ^ n)', '2^2^n'];
		yield 'a power as the left operand of a power' => ['(2 ^ 2) ^ n', '(2^2)^n'];
		yield 'a product as an operand of a power' => ['(a * b) ^ (c * d)', '(a * b)^(c * d)'];
		yield 'a power as an operand of a product' => ['a ^ 2 * b ^ 2', 'a^2 * b^2'];
		yield 'a negation' => ['- n', '-n'];
		yield 'a double negation' => ['-(-n)', '--n'];
		yield 'a negated sum' => ['-(a + b)', '-(a + b)'];
		yield 'a negated product' => ['-(a * b)', '-(a * b)'];
		yield 'a negated power' => ['-(n ^ 2)', '-n^2'];
		yield 'a power of a negation' => ['(-n) ^ 2', '(-n)^2'];
		yield 'a negative exponent' => ['n ^ (-1)', 'n^-1'];
		yield 'a negation as an operand of a product' => ['(-a) * (-b)', '-a * -b'];
		yield 'a negation as the right operand of a difference' => ['a - (-b)', 'a - -b'];
		yield 'a call' => ['log( n )', 'log(n)'];
		yield 'a call without arguments' => ['f( )', 'f()'];
		yield 'a call with arguments' => ['f(a,b+1)', 'f(a, b + 1)'];
		yield 'a key by name' => ["input['items']", 'input.items'];
		yield 'a key that is not a name' => ['input["a b"]', "input['a b']"];
		yield 'a key that starts with a digit' => ["input['1a']", "input['1a']"];
		yield 'a key with a single quote' => ['input["it\'s"]', 'input["it\'s"]'];
		yield 'a key by number' => ['rows [ 0 ]', 'rows[0]'];
		yield 'a numeric string key' => ["rows['0']", "rows['0']"];
		yield 'a key of a sum' => ['(a + b).c', '(a + b).c'];
		yield 'a key of a negation' => ['(-a).c', '(-a).c'];
		yield 'a key of a power' => ['(a ^ b).c', '(a^b).c'];
		yield 'a key of a call' => ['f(n).c', 'f(n).c'];
		yield 'a key of a key' => ['a.b.c', 'a.b.c'];
		yield 'a key of a number' => ['1 .x', '1.x'];
		yield 'every complexity of the table' => ['n*log(n)+n^2/log(log(n))-2^n*factorial(n)', 'n * log(n) + n^2 / log(log(n)) - 2^n * factorial(n)'];
	}

	/**
	 * @throws ExpressionException
	 */
	#[DataProvider('dataPrint')]
	public function testPrint(string $source, string $printed): void
	{
		$parser = new Parser(new Lexer());
		$dump = new AstDump();
		$expression = $parser->parse($source);
		$this->assertSame($printed, (new Printer())->print($expression->node));
		$this->assertSame($dump->tree($expression->node), $dump->tree($parser->parse($printed)->node), 'Parsing the printed expression gives the same tree.');
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function dataRoundTrip(): iterable
	{
		foreach (ParserTest::dataParse() as $name => [$source]) {
			yield $name => [$source];
		}
	}

	/**
	 * @throws ExpressionException
	 */
	#[DataProvider('dataRoundTrip')]
	public function testRoundTrip(string $source): void
	{
		$parser = new Parser(new Lexer());
		$dump = new AstDump();
		$expression = $parser->parse($source);
		$this->assertSame($dump->tree($expression->node), $dump->tree($parser->parse((new Printer())->print($expression->node))->node));
	}

}
