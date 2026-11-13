<?php declare(strict_types = 1);

namespace Tally\Cli;

use Tally\Comparison\Status;
use function intdiv;
use function sprintf;
use function str_repeat;
use function strlen;

/**
 * The progress of tally run, like PHPUnit's: a character for each finished case, and at the end
 * of each row how many of all cases have finished. . is ok, S slower, E not measured, N new, F faster.
 */
final class ProgressFormat
{

	private const COLUMNS = 80;

	/**
	 * What to write after a case finished.
	 *
	 * @param int $done the cases finished so far, from 1
	 */
	public function after(int $done, int $total, Status $status, bool $colors): string
	{
		$numberWidth = strlen((string) $total);
		$rowLength = self::COLUMNS - strlen('  /  (XXX%)') - 2 * $numberWidth;
		$column = ($done - 1) % $rowLength + 1;

		$output = $this->character($status, $colors);
		if ($column !== $rowLength && $done !== $total) {
			return $output;
		}

		return $output . str_repeat(' ', $rowLength - $column) . sprintf(
			' %*d / %*d (%3d%%)' . "\n",
			$numberWidth,
			$done,
			$numberWidth,
			$total,
			intdiv(100 * $done, $total),
		);
	}

	private function character(Status $status, bool $colors): string
	{
		[$character, $color] = match ($status) {
			Status::Ok => ['.', null],
			// the colors of the report's sections
			Status::Slower => ['S', '1;31'],
			Status::NotMeasured => ['E', '1;33'],
			Status::New => ['N', '1;34'],
			Status::Faster => ['F', '1;32'],
		};

		return $colors && $color !== null ? "\e[" . $color . 'm' . $character . "\e[0m" : $character;
	}

}
