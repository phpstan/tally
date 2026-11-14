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
use const PHP_INT_MAX;
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
		$oldBefore = 0;
		$newBefore = 0;
		$position = 0;
		foreach ($this->hunks($operations) as [$start, $end]) {
			foreach (array_slice($operations, $position, $start - $position) as [$kind]) {
				$oldBefore += $kind === '+' ? 0 : 1;
				$newBefore += $kind === '-' ? 0 : 1;
			}
			[$hunk, $oldCount, $newCount] = $this->hunk($operations, $start, $end, $oldBefore, $newBefore);
			$diff .= $hunk;
			$oldBefore += $oldCount;
			$newBefore += $newCount;
			$position = $end + 1;
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
	 * The shortest edit script of the changed middle. From the start, a common line is kept, and
	 * of removing and adding a line, the one that leaves fewer edits for the rest, removing on a
	 * tie, so removed lines come before added ones, like in GNU diff.
	 *
	 * @param list<string> $old
	 * @param list<string> $new
	 * @return list<array{' '|'-'|'+', string}>
	 */
	private function middle(array $old, array $new): array
	{
		$reach = $this->reach($old, $new);
		$edits = count($reach) - 1;
		$operations = [];
		$i = 0;
		$j = 0;
		while ($i < count($old) || $j < count($new)) {
			if ($i < count($old) && $j < count($new) && $this->at($old, $i) === $this->at($new, $j)) {
				$operations[] = [' ', $this->at($old, $i)];
				$i++;
				$j++;
				continue;
			}

			// the rest after removing the line, on diagonal i + 1 - j, takes one edit less, or one more
			if ($i < count($old) && ($j === count($new) || ($this->at($reach, $edits - 1)[$i + 1 - $j] ?? PHP_INT_MAX) <= $i + 1)) {
				$operations[] = ['-', $this->at($old, $i)];
				$i++;
			} else {
				$operations[] = ['+', $this->at($new, $j)];
				$j++;
			}
			$edits--;
		}

		return $operations;
	}

	/**
	 * Myers' search from the end: for each number of edits d, the first old line from which the
	 * rest of each diagonal k = i - j takes at most d edits. From a line i on diagonal k, the rest
	 * takes at most d edits exactly from that line on, because the edits do not grow toward the
	 * end of a diagonal. The search ends at d edits for everything, so it takes O((N + M) * d)
	 * time and O(d²) memory, nothing for the common lines.
	 *
	 * @param list<string> $old
	 * @param list<string> $new
	 * @return non-empty-list<array<int, int>> by the number of edits, the first old line by diagonal
	 */
	private function reach(array $old, array $new): array
	{
		$end = count($old) - count($new);
		$reach = [];
		for ($edits = 0;; $edits++) {
			$row = [];
			for ($k = $end - $edits; $k <= $end + $edits; $k += 2) {
				$i = $edits === 0 ? count($old) : $this->closest($this->at($reach, $edits - 1), $k);
				if ($i === null) {
					continue;
				}

				$j = $i - $k;
				while ($i > 0 && $j > 0 && $this->at($old, $i - 1) === $this->at($new, $j - 1)) {
					$i--;
					$j--;
				}
				$row[$k] = $i;
				if ($k === 0 && $i === 0) {
					return [...$reach, $row];
				}
			}
			$reach[] = $row;
		}
	}

	/**
	 * The first line of diagonal k one edit more reaches: by removing the line before the one diagonal
	 * k + 1 reaches, or by adding the line before the one diagonal k - 1 reaches.
	 *
	 * @param array<int, int> $previous the lines one edit less reaches
	 */
	private function closest(array $previous, int $k): ?int
	{
		$candidates = [];
		$removing = $previous[$k + 1] ?? 0;
		if ($removing > 0) {
			$candidates[] = $removing - 1;
		}
		$adding = $previous[$k - 1] ?? null;
		if ($adding !== null && $adding - $k >= 0) {
			$candidates[] = $adding;
		}

		return $candidates === [] ? null : min($candidates);
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
	 * @param int $oldBefore the old lines before the hunk
	 * @param int $newBefore the new lines before the hunk
	 * @return array{string, int, int} the hunk, and its old and new lines
	 */
	private function hunk(array $operations, int $start, int $end, int $oldBefore, int $newBefore): array
	{
		$oldCount = 0;
		$newCount = 0;
		$lines = [];
		foreach (array_slice($operations, $start, $end - $start + 1) as [$kind, $line]) {
			$oldCount += $kind === '+' ? 0 : 1;
			$newCount += $kind === '-' ? 0 : 1;
			$lines[] = $kind . $line . (str_ends_with($line, "\n") ? '' : "\n\\ No newline at end of file\n");
		}

		return [
			sprintf('@@ -%s +%s @@', $this->range($oldBefore, $oldCount), $this->range($newBefore, $newCount)) . "\n" . implode('', $lines),
			$oldCount,
			$newCount,
		];
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
