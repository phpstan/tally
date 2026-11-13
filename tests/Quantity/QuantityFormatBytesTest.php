<?php declare(strict_types = 1);

namespace PHPStan\Tally\Quantity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuantityFormatBytesTest extends TestCase
{

	/**
	 * @return iterable<string, array{int, string}>
	 */
	public static function dataFormat(): iterable
	{
		yield 'zero' => [0, '0B'];
		yield 'below a kibibyte keeps every digit' => [1023, '1023B'];
		yield 'a kibibyte, not a kilobyte' => [1024, '1.000KiB'];
		yield 'a thousand bytes are not a kibibyte' => [1000, '1000B'];
		yield 'four significant digits' => [46_647_360, '44.49MiB'];
		yield 'four digits before the point' => [1000 * 1024, '1000KiB'];
		yield 'rounding up into the next unit' => [1024 * 1024 - 10, '1.000MiB'];
		yield 'gibibytes' => [3 * 1024 * 1024 * 1024, '3.000GiB'];
	}

	#[DataProvider('dataFormat')]
	public function testFormat(int $value, string $expected): void
	{
		$this->assertSame($expected, QuantityFormat::bytes()->format($value));
	}

	/**
	 * @return iterable<string, array{string, int}>
	 */
	public static function dataParse(): iterable
	{
		yield 'mebibytes' => ['44.49MiB', 46_651_146];
		yield 'bytes with a unit' => ['1023B', 1023];
		yield 'bytes without a unit' => ['46_647_360', 46_647_360];
		yield 'kibibytes' => ['1KiB', 1024];
	}

	#[DataProvider('dataParse')]
	public function testParse(string $text, int $expected): void
	{
		$this->assertSame($expected, QuantityFormat::bytes()->parse($text));
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function dataParseRejects(): iterable
	{
		yield 'decimal megabytes are ambiguous' => ['44.49MB'];
		yield 'count unit' => ['1.2M'];
		yield 'fraction of a byte' => ['1.5B'];
	}

	#[DataProvider('dataParseRejects')]
	public function testParseRejects(string $text): void
	{
		$this->expectException(InvalidQuantityException::class);
		QuantityFormat::bytes()->parse($text);
	}

}
