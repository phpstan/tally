<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Expectation;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Expectation\Tokens;

final class TokensTest extends TestCase
{

	// 0 <?php, 1 $a, 2 whitespace, 3 =, 4 whitespace, 5 comment, 6 whitespace, 7 1, 8 ;
	private const CODE = '<?php $a = /* one */ 1;';

	/**
	 * @return iterable<string, array{int, int}>
	 */
	public static function dataNext(): iterable
	{
		yield 'the following token' => [0, 1];
		yield 'over whitespace' => [1, 3];
		yield 'over whitespace and comments' => [3, 7];
	}

	#[DataProvider('dataNext')]
	public function testNext(int $index, int $expected): void
	{
		$this->assertSame($expected, (new Tokens(self::CODE))->next($index));
	}

	/**
	 * @return iterable<string, array{int, int}>
	 */
	public static function dataPrevious(): iterable
	{
		yield 'the preceding token' => [8, 7];
		yield 'over whitespace and comments' => [7, 3];
		yield 'over whitespace' => [3, 1];
	}

	#[DataProvider('dataPrevious')]
	public function testPrevious(int $index, int $expected): void
	{
		$this->assertSame($expected, (new Tokens(self::CODE))->previous($index));
	}

	public function testSkipTo(): void
	{
		$tokens = new Tokens(self::CODE);

		$this->assertSame(3, $tokens->skipTo(1, '='));
		$this->assertSame(3, $tokens->skipTo(3, '='));
		$this->assertSame(8, $tokens->skipTo(3, ';'));
	}

	public function testAtAndCount(): void
	{
		$tokens = new Tokens(self::CODE);

		$this->assertSame(9, $tokens->count());
		$this->assertSame('$a', $tokens->at(1)->text);
		$this->assertSame(';', $tokens->at(8)->text);
	}

	public function testAtFailsPastTheEnd(): void
	{
		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('The code has no token 9.');
		(new Tokens(self::CODE))->at(9);
	}

}
