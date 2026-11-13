<?php declare(strict_types = 1);

namespace Tally\Report;

use Closure;
use Tally\Comparison\Status;
use function array_filter;
use function array_map;
use function count;
use function str_replace;

/**
 * The report for a terminal or a CI log: a headline, the allowed differences, a table per section
 * that needs attention, and the commands to reproduce the worst case and to accept the numbers.
 */
final class ConsoleReport
{

	private const STATUS_COLUMN = 6;

	public function __construct(private TextTable $table, private Rows $rows)
	{
	}

	/**
	 * @param string $reproduceCommand runs the worst case
	 * @param string $acceptCommand shell commands that accept the measured numbers
	 */
	public function summary(Report $report, string $reproduceCommand, string $acceptCommand, bool $colors): string
	{
		$output = $this->style($colors, $report->fails() ? '1;31' : '1;32', $report->headline()) . "\n";
		foreach ($report->tolerances() as $tolerance) {
			$output .= 'Allowed difference: ' . $tolerance . "\n";
		}

		$sections = $report->sections();
		foreach ($sections as $section) {
			$output .= "\n" . $this->style($colors, '1;' . $this->color($section->status), $section->title()) . "\n";
			$output .= $section->text() . "\n\n";
			if ($section->status === Status::NotMeasured) {
				$rows = [Rows::FAILURE_HEADER, ...array_map($this->rows->failure(...), $section->cases)];
				$output .= $this->table->render($rows, [], '  ', $this->header($colors));
				continue;
			}

			$rows = [Rows::HEADER];
			foreach ($section->cases as $case) {
				$rows = [...$rows, ...$this->rows->metrics($case)];
			}
			$output .= $this->table->render($rows, Rows::NUMBER_COLUMNS, '  ', $this->header($colors));
		}
		if ($sections === []) {
			return $output;
		}

		$output .= "\n" . $this->style($colors, '1', 'Reproduce locally') . "\n";
		$output .= '  ' . $reproduceCommand . "\n";
		$toAccept = array_filter($sections, static fn (Section $section): bool => $section->status !== Status::NotMeasured);
		if ($toAccept !== []) {
			$output .= "\n" . $this->style($colors, '1', 'Accept the new numbers') . "\n";
			$output .= '  ' . str_replace("\n", "\n  ", $acceptCommand) . "\n";
		}

		return $output;
	}

	/**
	 * Every case with its metrics and status.
	 */
	public function table(Report $report, bool $colors): string
	{
		$rows = [[...Rows::HEADER, 'Status']];
		// the colors of the statuses that need attention, by row
		$colorsByRow = [];
		foreach ($report->cases as $case) {
			foreach ($this->rows->metrics($case) as $i => $row) {
				$status = $case->metrics[$i]->status ?? $case->status;
				if ($status !== Status::Ok) {
					$colorsByRow[count($rows)] = $this->color($status);
				}
				$rows[] = [...$row, $status->value];
			}
		}

		return $this->table->render($rows, Rows::NUMBER_COLUMNS, '', function (int $row, int $column, string $cell) use ($colors, $colorsByRow): string {
			if ($row === 0) {
				return $this->style($colors, '1', $cell);
			}
			if ($column !== self::STATUS_COLUMN || !isset($colorsByRow[$row])) {
				return $cell;
			}

			return $this->style($colors, $colorsByRow[$row], $cell);
		});
	}

	private function color(Status $status): string
	{
		return match ($status) {
			Status::Slower => '31',
			Status::NotMeasured => '33',
			Status::New => '34',
			Status::Faster, Status::Ok => '32',
		};
	}

	/**
	 * @return Closure(int, int, string): string
	 */
	private function header(bool $colors): Closure
	{
		return fn (int $row, int $column, string $cell): string => $row === 0 ? $this->style($colors, '1', $cell) : $cell;
	}

	private function style(bool $colors, string $code, string $text): string
	{
		return $colors ? "\e[" . $code . 'm' . $text . "\e[0m" : $text;
	}

}
