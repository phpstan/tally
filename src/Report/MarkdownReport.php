<?php declare(strict_types = 1);

namespace Tally\Report;

use Tally\Comparison\Status;
use function array_keys;
use function array_map;
use function array_shift;
use function implode;
use function in_array;
use function max;
use function preg_match_all;
use function rtrim;
use function sprintf;
use function str_repeat;
use function str_replace;
use function strlen;

/** The report for a GitHub Actions job summary, with alerts for the sections and every case collapsed. */
final class MarkdownReport
{

	public function __construct(private Rows $rows)
	{
	}

	public function render(Report $report, string $reproduceCommand, string $acceptCommand, NewExpectations $newExpectations): string
	{
		$output = '## ' . $report->headline() . "\n\n";
		foreach ($report->tolerances() as $tolerance) {
			$output .= 'Allowed difference: ' . $tolerance . "\n\n";
		}

		$sections = $report->sections();
		foreach ($sections as $section) {
			$output .= sprintf("> [!%s]\n> **%s**\n> %s\n\n", $this->alert($section->status), $section->title(), $section->text());
			if ($section->status === Status::NotMeasured) {
				foreach ($section->cases as $case) {
					$output .= sprintf("`%s`\n\n%s\n", $case->id->toString(), $this->codeBlock('text', $case->failure ?? ''));
				}
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
			if ($report->hasNumbersToAccept()) {
				$output .= "### Accept the new numbers\n\n```\n" . $acceptCommand . "\n```\n\n";
			}
		}
		$output .= $this->newExpectations($newExpectations);

		$rows = [[...Rows::HEADER, 'Status']];
		foreach ($report->cases as $case) {
			foreach ($this->rows->metrics($case) as $i => $row) {
				$rows[] = [...$row, ($case->metrics[$i]->status ?? $case->status)->value];
			}
		}

		return $output . "<details><summary>All benchmarks</summary>\n\n" . $this->table($rows, Rows::NUMBER_COLUMNS) . "\n</details>\n";
	}

	private function newExpectations(NewExpectations $newExpectations): string
	{
		if ($newExpectations->isEmpty()) {
			return '';
		}

		$output = '### ' . NewExpectations::TITLE . "\n\n";
		foreach ($newExpectations->failures as $id => $failure) {
			$output .= sprintf("- Cannot add the expected numbers of `%s`: %s\n", $id, $failure);
		}
		if ($newExpectations->failures !== []) {
			$output .= "\n";
		}
		if ($newExpectations->patch === '') {
			return $output;
		}

		return $output . NewExpectations::TEXT . "\n\n" . $this->codeBlock('diff', $newExpectations->patch) . "\n";
	}

	/** A fence longer than any run of backticks in the code */
	private function codeBlock(string $language, string $code): string
	{
		$fence = str_repeat('`', max(3, 1 + max([0, ...array_map(strlen(...), $this->backtickRuns($code))])));

		return $fence . $language . "\n" . rtrim($code, "\n") . "\n" . $fence . "\n";
	}

	/**
	 * @return list<string>
	 */
	private function backtickRuns(string $text): array
	{
		preg_match_all('~`+~', $text, $matches);

		return $matches[0];
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
