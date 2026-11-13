<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Attribute\Expected;
use Tally\Quantity\InvalidQuantityException;

final class ExpectedTest extends TestCase
{

	/**
	 * @return iterable<string, array{Expected, array<string, int>}>
	 */
	public static function dataMetrics(): iterable
	{
		yield 'none' => [new Expected(), []];
		yield 'every metric' => [new Expected('1.204K', '1.387K', '1.000KiB'), ['instructions' => 1204, 'cycles' => 1387, 'memory' => 1024]];
		yield 'only cycles' => [new Expected(cycles: '5'), ['cycles' => 5]];
		yield 'only memory' => [new Expected(memory: '0B'), ['memory' => 0]];
	}

	/**
	 * @param array<string, int> $expected
	 * @throws InvalidQuantityException
	 */
	#[DataProvider('dataMetrics')]
	public function testMetrics(Expected $attribute, array $expected): void
	{
		$this->assertSame($expected, $attribute->metrics()->toArray());
	}

	/**
	 * @throws InvalidQuantityException
	 */
	public function testMetricsRejectsAMemoryCount(): void
	{
		$this->expectException(InvalidQuantityException::class);
		$this->expectExceptionMessage('"1.2K" is not a quantity, such as 3.119B or 44.49MiB. Units: B, KiB, MiB, GiB, TiB.');
		(new Expected(memory: '1.2K'))->metrics();
	}

}
