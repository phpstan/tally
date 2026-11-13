<?php declare(strict_types = 1);

namespace Tally\Report;

use Tally\Comparison\Status;
use function array_filter;
use function array_keys;
use function array_map;
use function array_shift;
use function implode;
use function in_array;
use function sprintf;
use function str_replace;

/** The report for a GitHub Actions job summary, with alerts for the sections and every case collapsed. */
final class MarkdownReport
{

	public function __construct(private Rows $rows)
	{
	}

	public function render(Report $report, string $reproduceCommand, string $acceptCommand): string
	{
		$output = '## ' . $report->headline() . "\n\n";
		foreach ($report->tolerances() as $tolerance) {
			$output .= 'Allowed difference: ' . $tolerance . "\n\n";
		}

		$sections = $report->sections();
		foreach ($sections as $section) {
			$output .= sprintf("> [!%s]\n> **%s**\n> %s\n\n", $this->alert($section->status), $section->title(), $section->text());
			if ($section->status === Status::NotMeasured) {
				$output .= $this->table([Rows::FAILURE_HEADER, ...array_map($this->rows->failure(...), $section->cases)], []) . "\n";
				continue;
			}

			$rows = [Rows::HEADER];
			foreach ($section->cases as $case) {
				$rows = [...$rows, ...$this->rows->metrics($case)];
			}
			$output .= $this->table($rows, Rows::NUMBER_COLUMNS) . "\n";
		}
		if ($sections !== []) {
			$output .= "### Reproduce locally\n\n```\n" . $reproduceCommand . "\n```\n\n";
			if (array_filter($sections, static fn (Section $section): bool => $section->status !== Status::NotMeasured) !== []) {
				$output .= "### Accept the new numbers\n\n```\n" . $acceptCommand . "\n```\n\n";
			}
		}

		$rows = [[...Rows::HEADER, 'Status']];
		foreach ($report->cases as $case) {
			foreach ($this->rows->metrics($case) as $i => $row) {
				$rows[] = [...$row, ($case->metrics[$i]->status ?? $case->status)->value];
			}
		}

		return $output . "<details><summary>All benchmarks</summary>\n\n" . $this->table($rows, Rows::NUMBER_COLUMNS) . "\n</details>\n";
	}

	private function alert(Status $status): string
	{
		return match ($status) {
			Status::Slower => 'CAUTION',
			Status::NotMeasured => 'WARNING',
			Status::New => 'NOTE',
			Status::Faster, Status::Ok => 'TIP',
		};
	}

	/**
	 * @param non-empty-list<list<string>> $rows the first one is the header
	 * @param list<int> $rightAligned
	 */
	private function table(array $rows, array $rightAligned): string
	{
		$header = array_shift($rows);
		$output = '| ' . implode(' | ', $header) . " |\n";
		$output .= '|' . implode('|', array_map(static fn (int $column): string => in_array($column, $rightAligned, true) ? '--:' : '---', array_keys($header))) . "|\n";
		foreach ($rows as $row) {
			$cells = [];
			foreach ($row as $column => $cell) {
				$escaped = str_replace('|', '\|', $cell);
				// the case's ID as code
				$cells[] = $column === 0 && $cell !== '' ? '`' . $escaped . '`' : $escaped;
			}
			$output .= '| ' . implode(' | ', $cells) . " |\n";
		}

		return $output;
	}

}
