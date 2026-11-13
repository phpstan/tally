<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Cli\ProgressFormat;
use Tally\Comparison\Status;
use function array_fill;
use function count;
use function str_repeat;

final class ProgressFormatTest extends TestCase
{

	/**
	 * @return iterable<string, array{list<Status>, string}>
	 */
	public static function progressProvider(): iterable
	{
		// 80 columns minus the counter of 1-digit numbers is 67
		yield 'one row' => [
			[Status::Ok, Status::Slower, Status::NotMeasured, Status::New, Status::Faster],
			'.SENF' . str_repeat(' ', 62) . ' 5 / 5 (100%)' . "\n",
		];
		// with 2-digit numbers, 65
		yield 'a full row ends with the counter' => [
			array_fill(0, 65, Status::Ok),
			str_repeat('.', 65) . ' 65 / 65 (100%)' . "\n",
		];
		yield 'a row is full before the last case' => [
			array_fill(0, 70, Status::Ok),
			str_repeat('.', 65) . ' 65 / 70 ( 92%)' . "\n" . '.....' . str_repeat(' ', 60) . ' 70 / 70 (100%)' . "\n",
		];
		// with 3-digit numbers, 63
		yield 'a full row of 3-digit numbers' => [
			array_fill(0, 100, Status::Ok),
			str_repeat('.', 63) . '  63 / 100 ( 63%)' . "\n" . str_repeat('.', 37) . str_repeat(' ', 26) . ' 100 / 100 (100%)' . "\n",
		];
	}

	/**
	 * @param list<Status> $statuses
	 */
	#[DataProvider('progressProvider')]
	public function testWritesACharacterPerCaseAndTheCounterAtTheEndOfEachRow(array $statuses, string $expected): void
	{
		$format = new ProgressFormat();
		$output = '';
		foreach ($statuses as $i => $status) {
			$output .= $format->after($i + 1, count($statuses), $status, false);
		}

		$this->assertSame($expected, $output);
	}

	public function testWritesOnlyTheCharacterWithinARowAndRoundsThePercentageDown(): void
	{
		$format = new ProgressFormat();

		$this->assertSame('.', $format->after(1, 3, Status::Ok, false));
		// 1 of 3 is 33 %
		$this->assertSame('.' . str_repeat(' ', 64) . ' 3 / 3 (100%)' . "\n", $format->after(3, 3, Status::Ok, false));
		$this->assertSame('.  63 / 200 ( 31%)' . "\n", $format->after(63, 200, Status::Ok, false));
		$this->assertSame('.', $format->after(64, 200, Status::Ok, false));
	}

	public function testColorsTheStatusesThatNeedAttention(): void
	{
		$format = new ProgressFormat();

		$this->assertSame('.', $format->after(1, 9, Status::Ok, true));
		$this->assertSame("\e[37;41mS\e[0m", $format->after(1, 9, Status::Slower, true));
		$this->assertSame("\e[30;43mE\e[0m", $format->after(1, 9, Status::NotMeasured, true));
		$this->assertSame("\e[34mN\e[0m", $format->after(1, 9, Status::New, true));
		$this->assertSame("\e[32mF\e[0m", $format->after(1, 9, Status::Faster, true));
	}

}
