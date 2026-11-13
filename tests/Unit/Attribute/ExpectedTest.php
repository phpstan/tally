<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Attribute\Expected;
use Tally\Attribute\ExpectedFor;
use Tally\Quantity\InvalidQuantityException;

final class ExpectedTest extends TestCase
{

	/**
	 * @return iterable<string, array{Expected|ExpectedFor, array<string, int>}>
	 */
	public static function dataMetrics(): iterable
	{
		yield 'every metric' => [new Expected('1.204K', '1.387K', '1.000KiB'), ['instructions' => 1204, 'cycles' => 1387, 'memory' => 1024]];
		yield 'plain numbers' => [new Expected('5', '6', '0B'), ['instructions' => 5, 'cycles' => 6, 'memory' => 0]];
		yield 'of a data set' => [new ExpectedFor('small', '18.20K', '21.04K', '4.000KiB'), ['instructions' => 18_200, 'cycles' => 21_040, 'memory' => 4096]];
	}

	/**
	 * @param array<string, int> $expected
	 * @throws InvalidQuantityException
	 */
	#[DataProvider('dataMetrics')]
	public function testMetrics(Expected|ExpectedFor $attribute, array $expected): void
	{
		$this->assertSame($expected, $attribute->metrics()->toArray());
	}

	/**
	 * @return iterable<string, array{Expected|ExpectedFor, string}>
	 */
	public static function dataInvalid(): iterable
	{
		yield 'memory as a count' => [new Expected('1', '1', '1.2K'), '"1.2K" is not a quantity, such as 3.119B or 44.49MiB. Units: B, KiB, MiB, GiB, TiB.'];
		yield 'instructions in bytes' => [new Expected('1KiB', '1', '0B'), '"1KiB" is not a quantity'];
		yield 'cycles of a data set' => [new ExpectedFor('a', '1', '1X', '0B'), '"1X" is not a quantity'];
	}

	/**
	 * @throws InvalidQuantityException
	 */
	#[DataProvider('dataInvalid')]
	public function testInvalid(Expected|ExpectedFor $attribute, string $message): void
	{
		$this->expectException(InvalidQuantityException::class);
		$this->expectExceptionMessage($message);
		$attribute->metrics();
	}

}
