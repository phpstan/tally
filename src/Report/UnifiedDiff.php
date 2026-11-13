<?php declare(strict_types = 1);

namespace Tally\Report;

use LogicException;
use function array_slice;
use function count;
use function implode;
use function max;
use function min;
use function preg_split;
use function sprintf;
use function str_ends_with;
use const PREG_SPLIT_NO_EMPTY;

/**
 * Renders the change of a file as a unified diff that git apply and patch -p1 apply, with the
 * hunks GNU diff -u would print. The common start and end of both versions are skipped before the
 * shortest edit script of the rest is searched, because rewriting expectations changes a few
 * lines of a file.
 */
final class UnifiedDiff
{

	private const CONTEXT_LINES = 3;

	/**
	 * @param string $path relative to the repository, without the a/ and b/ prefixes
	 * @return string nothing when the versions are equal
	 */
	public function render(string $path, string $before, string $after): string
	{
		if ($before === $after) {
			return '';
		}

		$operations = $this->operations($this->lines($before), $this->lines($after));
		$diff = sprintf("--- a/%s\n+++ b/%1\$s\n", $path);
		foreach ($this->hunks($operations) as [$start, $end]) {
			$diff .= $this->hunk($operations, $start, $end);
		}

		return $diff;
	}

	/**
	 * @return list<string> with their line breaks, the last one without when the text does not end with one
	 */
	private function lines(string $text): array
	{
		$lines = preg_split('~(?<=\n)~', $text, -1, PREG_SPLIT_NO_EMPTY);

		return $lines === false ? [] : $lines;
	}

	/**
	 * @param list<string> $old
	 * @param list<string> $new
	 * @return list<array{' '|'-'|'+', string}>
	 */
	private function operations(array $old, array $new): array
	{
		$prefix = 0;
		while ($prefix < min(count($old), count($new)) && $this->at($old, $prefix) === $this->at($new, $prefix)) {
			$prefix++;
		}
		$suffix = 0;
		while (
			$suffix < min(count($old), count($new)) - $prefix
			&& $this->at($old, count($old) - 1 - $suffix) === $this->at($new, count($new) - 1 - $suffix)
		) {
			$suffix++;
		}

		$operations = [];
		foreach (array_slice($old, 0, $prefix) as $line) {
			$operations[] = [' ', $line];
		}
		$operations = [
			...$operations,
			...$this->middle(array_slice($old, $prefix, count($old) - $prefix - $suffix), array_slice($new, $prefix, count($new) - $prefix - $suffix)),
		];
		foreach (array_slice($old, count($old) - $suffix) as $line) {
			$operations[] = [' ', $line];
		}

		return $operations;
	}

	/**
	 * The shortest edit script of the changed middle, from the number of lines to remove and add
	 * to turn each suffix of the old lines into each suffix of the new ones. Removed lines come
	 * before added ones, like in GNU diff.
	 *
	 * @param list<string> $old
	 * @param list<string> $new
	 * @return list<array{' '|'-'|'+', string}>
	 */
	private function middle(array $old, array $new): array
	{
		/** @var array<int, array<int, int>> $edits */
		$edits = [];
		for ($i = count($old); $i >= 0; $i--) {
			$row = [];
			for ($j = count($new); $j >= 0; $j--) {
				if ($i === count($old)) {
					$row[$j] = count($new) - $j;
				} elseif ($j === count($new)) {
					$row[$j] = count($old) - $i;
				} elseif ($this->at($old, $i) === $this->at($new, $j)) {
					$row[$j] = $this->at($this->at($edits, $i + 1), $j + 1);
				} else {
					$row[$j] = 1 + min($this->at($this->at($edits, $i + 1), $j), $this->at($row, $j + 1));
				}
			}
			$edits[$i] = $row;
		}

		$operations = [];
		$i = 0;
		$j = 0;
		while ($i < count($old) || $j < count($new)) {
			if ($i < count($old) && $j < count($new) && $this->at($old, $i) === $this->at($new, $j)) {
				$operations[] = [' ', $this->at($old, $i)];
				$i++;
				$j++;
			} elseif ($i < count($old) && ($j === count($new) || $this->at($this->at($edits, $i + 1), $j) <= $this->at($this->at($edits, $i), $j + 1))) {
				$operations[] = ['-', $this->at($old, $i)];
				$i++;
			} else {
				$operations[] = ['+', $this->at($new, $j)];
				$j++;
			}
		}

		return $operations;
	}

	/**
	 * @param list<array{' '|'-'|'+', string}> $operations
	 * @return list<array{int, int}> the first and the last operation of each hunk
	 */
	private function hunks(array $operations): array
	{
		$hunks = [];
		$current = null;
		foreach ($operations as $index => [$kind]) {
			if ($kind === ' ') {
				continue;
			}

			$start = max(0, $index - self::CONTEXT_LINES);
			$end = min(count($operations) - 1, $index + self::CONTEXT_LINES);
			// the context of two changes overlaps or touches
			if ($current !== null && $start <= $current[1] + 1) {
				$current = [$current[0], $end];
				continue;
			}
			if ($current !== null) {
				$hunks[] = $current;
			}
			$current = [$start, $end];
		}
		if ($current !== null) {
			$hunks[] = $current;
		}

		return $hunks;
	}

	/**
	 * @param list<array{' '|'-'|'+', string}> $operations
	 */
	private function hunk(array $operations, int $start, int $end): string
	{
		$oldBefore = 0;
		$newBefore = 0;
		foreach (array_slice($operations, 0, $start) as [$kind]) {
			$oldBefore += $kind === '+' ? 0 : 1;
			$newBefore += $kind === '-' ? 0 : 1;
		}

		$oldCount = 0;
		$newCount = 0;
		$lines = [];
		foreach (array_slice($operations, $start, $end - $start + 1) as [$kind, $line]) {
			$oldCount += $kind === '+' ? 0 : 1;
			$newCount += $kind === '-' ? 0 : 1;
			$lines[] = $kind . $line . (str_ends_with($line, "\n") ? '' : "\n\\ No newline at end of file\n");
		}

		return sprintf('@@ -%s +%s @@', $this->range($oldBefore, $oldCount), $this->range($newBefore, $newCount)) . "\n" . implode('', $lines);
	}

	/** Like GNU diff: an empty range starts at the line before it, a range of one line has no count */
	private function range(int $before, int $count): string
	{
		if ($count === 1) {
			return (string) ($before + 1);
		}

		return sprintf('%d,%d', $count === 0 ? $before : $before + 1, $count);
	}

	/**
	 * @template T
	 * @param array<int, T> $items
	 * @return T
	 */
	private function at(array $items, int $index): mixed
	{
		return $items[$index] ?? throw new LogicException(sprintf('The diff reads item %d, which does not exist.', $index));
	}

}
