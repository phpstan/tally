<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Expression;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Expression\ExpressionException;
use Tally\Expression\Lexer;
use Tally\Expression\Token;
use Tally\Expression\TokenType;
use function array_map;
use function implode;

final class LexerTest extends TestCase
{

	/**
	 * @return iterable<string, array{string, list<string>}>
	 */
	public static function dataTokenize(): iterable
	{
		yield 'empty' => ['', ['End@0']];
		yield 'only spaces' => ["  \t ", ['End@4']];
		yield 'a name' => ['n', ['Name(n)@0', 'End@1']];
		yield 'a name with digits and underscores' => ['_input_2', ['Name(_input_2)@0', 'End@8']];
		yield 'a name in upper case' => ['Items', ['Name(Items)@0', 'End@5']];
		yield 'a whole number' => ['120', ['Number(120)@0', 'End@3']];
		yield 'a decimal number' => ['0.25', ['Number(0.25)@0', 'End@4']];
		yield 'a number followed by a key' => ['1.x', ['Number(1)@0', 'Dot(.)@1', 'Name(x)@2', 'End@3']];
		yield 'a single-quoted string' => ["'items'", ["String('items')@0", 'End@7']];
		yield 'a double-quoted string' => ['"it\'s"', ['String("it\'s")@0', 'End@6']];
		yield 'an empty string' => ["''", ["String('')@0", 'End@2']];
		yield 'every symbol' => ['+-*/^()[].,', [
			'Plus(+)@0',
			'Minus(-)@1',
			'Star(*)@2',
			'Slash(/)@3',
			'Caret(^)@4',
			'LeftParen(()@5',
			'RightParen())@6',
			'LeftBracket([)@7',
			'RightBracket(])@8',
			'Dot(.)@9',
			'Comma(,)@10',
			'End@11',
		]];
		yield 'offsets skip spaces' => [' n  *log( n ) ', [
			'Name(n)@1',
			'Star(*)@4',
			'Name(log)@5',
			'LeftParen(()@8',
			'Name(n)@10',
			'RightParen())@12',
			'End@14',
		]];
		yield 'a path' => ["input.items[0]['a b']", [
			'Name(input)@0',
			'Dot(.)@5',
			'Name(items)@6',
			'LeftBracket([)@11',
			'Number(0)@12',
			'RightBracket(])@13',
			'LeftBracket([)@14',
			"String('a b')@15",
			'RightBracket(])@20',
			'End@21',
		]];
	}

	/**
	 * @param list<string> $expected
	 * @throws ExpressionException
	 */
	#[DataProvider('dataTokenize')]
	public function testTokenize(string $source, array $expected): void
	{
		$tokens = (new Lexer())->tokenize($source)->all();
		$this->assertSame($expected, array_map(static fn (Token $token): string => match ($token->type) {
			TokenType::End => 'End@' . $token->offset,
			default => $token->type->name . '(' . $token->text . ')@' . $token->offset,
		}, $tokens));
	}

	/**
	 * @return iterable<string, array{string, list<string>}>
	 */
	public static function dataInvalid(): iterable
	{
		yield 'an unknown character' => ['n % 2', [
			'Unexpected character "%".',
			'  n % 2',
			'    ^',
		]];
		yield 'a newline' => ["n\n+ 1", [
			'Unexpected character "\\n".',
			'  n',
			'   ^',
		]];
		yield 'an unclosed single-quoted string' => ["input['items", [
			'The string is not closed, end it with \'.',
			"  input['items",
			'        ^^^^^^',
		]];
		yield 'an unclosed double-quoted string' => ['input["items', [
			'The string is not closed, end it with ".',
			'  input["items',
			'        ^^^^^^',
		]];
		yield 'a whole number too large' => ['n * 99999999999999999999', [
			'The number 99999999999999999999 is too large.',
			'  n * 99999999999999999999',
			'      ^^^^^^^^^^^^^^^^^^^^',
		]];
	}

	/**
	 * @param list<string> $message
	 */
	#[DataProvider('dataInvalid')]
	public function testInvalid(string $source, array $message): void
	{
		try {
			(new Lexer())->tokenize($source);
			self::fail('No exception.');
		} catch (ExpressionException $e) {
			$this->assertSame(implode("\n", $message), $e->getMessage());
		}
	}

}
