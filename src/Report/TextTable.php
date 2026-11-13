<?php declare(strict_types = 1);

namespace Tally\Report;

use Closure;
use function array_map;
use function implode;
use function in_array;
use function max;
use function mb_strlen;
use function rtrim;
use function str_repeat;

/** Lays out rows in columns for a terminal: text left, numbers right. */
final class TextTable
{

	private const COLUMN_GAP = '   ';

	/**
	 * @param non-empty-list<list<string>> $rows the first one is the header
	 * @param list<int> $rightAligned the columns aligned to the right
	 * @param Closure(int, int, string): string $style styles a padded cell by its row and column, the header is row 0
	 */
	public function render(array $rows, array $rightAligned, string $indent, Closure $style): string
	{
		$columns = [];
		foreach ($rows as $rowIndex => $row) {
			foreach ($row as $column => $cell) {
				$columns[$column][$rowIndex] = $cell;
			}
		}

		$styled = [];
		foreach ($columns as $column => $cells) {
			$width = max(array_map(mb_strlen(...), $cells));
			$right = in_array($column, $rightAligned, true);
			foreach ($cells as $rowIndex => $cell) {
				// by characters, the cells have ± and ×
				$spaces = str_repeat(' ', $width - mb_strlen($cell));
				$styled[$rowIndex][$column] = $style($rowIndex, $column, $right ? $spaces . $cell : $cell . $spaces);
			}
		}

		$lines = [];
		foreach ($styled as $cells) {
			$lines[] = rtrim($indent . implode(self::COLUMN_GAP, $cells));
		}

		return implode("\n", $lines) . "\n";
	}

}
