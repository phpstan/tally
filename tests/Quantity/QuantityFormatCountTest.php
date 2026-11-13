<?php declare(strict_types = 1);

namespace PHPStan\Tally\Quantity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function abs;

class QuantityFormatCountTest extends TestCase
{

	/**
	 * @return iterable<string, array{int, string}>
	 */
	public static function dataFormat(): iterable
	{
		yield 'zero' => [0, '0'];
		yield 'below a thousand keeps every digit' => [999, '999'];
		yield 'a thousand' => [1000, '1.000K'];
		yield 'four significant digits' => [3_118_976_850, '3.119B'];
		yield 'two decimals' => [11_340_000_000, '11.34B'];
		yield 'one decimal' => [260_863_142, '260.9M'];
		yield 'rounding up into the next unit' => [999_950, '1.000M'];
		yield 'just below rounding into the next unit' => [999_949, '999.9K'];
		yield 'trillions' => [1_500_000_000_000, '1.500T'];
		yield 'beyond the largest unit' => [12_345_000_000_000_000, '12345T'];
		yield 'negative, after subtracting a larger baseline' => [-1234, '-1.234K'];
	}

	#[DataProvider('dataFormat')]
	public function testFormat(int $value, string $expected): void
	{
		$this->assertSame($expected, QuantityFormat::count()->format($value));
	}

	/**
	 * @return iterable<string, array{string, int}>
	 */
	public static function dataParse(): iterable
	{
		yield 'formatted' => ['3.119B', 3_119_000_000];
		yield 'plain' => ['312', 312];
		yield 'underscores' => ['3_118_976_850', 3_118_976_850];
		yield 'space before the unit' => ['260.9 M', 260_900_000];
		yield 'negative' => ['-1.234K', -1234];
		yield 'no decimals' => ['12345T', 12_345_000_000_000_000];
	}

	#[DataProvider('dataParse')]
	public function testParse(string $text, int $expected): void
	{
		$this->assertSame($expected, QuantityFormat::count()->parse($text));
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function dataParseRejects(): iterable
	{
		yield 'unknown unit' => ['1.2X'];
		yield 'byte unit' => ['1.2MiB'];
		yield 'lowercase unit' => ['1.2k'];
		yield 'empty' => [''];
		yield 'only a unit' => ['K'];
		yield 'fraction of one' => ['1.5'];
	}

	#[DataProvider('dataParseRejects')]
	public function testParseRejects(string $text): void
	{
		$this->expectException(InvalidQuantityException::class);
		QuantityFormat::count()->parse($text);
	}

	public function testRoundTripMovesValuesByAtMostHalfTheLastDigit(): void
	{
		$format = QuantityFormat::count();
		foreach ([1, 999, 1001, 54_321, 999_949, 999_950, 7_654_321, 3_118_976_850, 98_765_432_109] as $value) {
			$text = $format->format($value);
			$parsed = $format->parse($text);
			$this->assertLessThanOrEqual(0.0005, abs($parsed - $value) / $value, $text);
			$this->assertSame($text, $format->format($parsed), 'formatting again does not churn');
		}
	}

}
