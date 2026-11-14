<?php declare(strict_types = 1);

namespace Tally\Expectation;

use Tally\BenchmarkCase;
use Tally\Metrics;
use function array_diff_key;
use function array_keys;
use function sprintf;
use function strnatcmp;
use function substr;
use function usort;

/**
 * Plans the new code of the files that keep the expectations of the accepted cases. The edits of
 * all cases of a file are applied together, in one pass over its code. ExpectationFiles reads and
 * writes the files.
 */
final class ExpectationChanges
{

	/**
	 * @param list<array{BenchmarkCase, Metrics}> $accepted the cases with their measured metrics
	 * @param array<string, string|false> $code the code of the files by path, false for a file that cannot be read
	 */
	public function plan(array $accepted, array $code): ChangePlan
	{
		/** @var array<string, array{string, array<string, list<CodeEdit>>}> $editsByFile the code and the edits by case */
		$editsByFile = [];
		$failures = [];
		foreach ($accepted as [$case, $measured]) {
			$id = $case->id->toString();
			$file = $case->expectations->file();
			$before = $code[$file] ?? false;
			if ($before === false) {
				$failures[$id] = sprintf('Cannot read %s.', $file);
				continue;
			}

			try {
				$edits = $case->expectations->edits($before, $measured);
			} catch (ExpectationException $e) {
				$failures[$id] = $e->getMessage();
				continue;
			}
			$editsByFile[$file] ??= [$before, []];
			$editsByFile[$file][1][$id] = $edits;
		}

		$files = [];
		foreach ($editsByFile as $file => [$before, $editsByCase]) {
			$sorted = $this->sorted($editsByCase);
			$overlapping = $this->overlapping($sorted);
			$failures = [...$failures, ...$overlapping];
			$files[] = new FileChange($file, $before, $this->apply($before, $sorted, $overlapping), array_keys(array_diff_key($editsByCase, $overlapping)));
		}

		return new ChangePlan($files, $failures);
	}

	/**
	 * @param array<string, list<CodeEdit>> $editsByCase
	 * @return list<array{CodeEdit, string}> the edits with their cases, by offset, insertions before
	 *     replacements, and insertions at one offset in the natural order of their text
	 */
	private function sorted(array $editsByCase): array
	{
		$edits = [];
		foreach ($editsByCase as $id => $caseEdits) {
			foreach ($caseEdits as $edit) {
				$edits[] = [$edit, $id];
			}
		}
		usort($edits, static function (array $a, array $b): int {
			$order = [$a[0]->offset, $a[0]->length] <=> [$b[0]->offset, $b[0]->length];

			return $order !== 0 ? $order : strnatcmp($a[0]->text, $b[0]->text);
		});

		return $edits;
	}

	/**
	 * @param list<array{CodeEdit, string}> $edits sorted
	 * @return array<string, string> the cases with an edit that starts inside an earlier one, with why
	 */
	private function overlapping(array $edits): array
	{
		$overlapping = [];
		$previous = null;
		$previousCase = '';
		foreach ($edits as [$edit, $id]) {
			if ($this->equal($edit, $previous)) {
				continue;
			}
			if ($previous !== null && $edit->offset < $previous->offset + $previous->length) {
				$overlapping[$id] = sprintf('Its expectations overlap those of %s.', $previousCase);
				continue;
			}

			$previous = $edit;
			$previousCase = $id;
		}

		return $overlapping;
	}

	/**
	 * @param list<array{CodeEdit, string}> $edits sorted
	 * @param array<string, string> $overlapping the cases whose edits are left out
	 */
	private function apply(string $code, array $edits, array $overlapping): string
	{
		$after = '';
		$position = 0;
		$previous = null;
		foreach ($edits as [$edit, $id]) {
			if (isset($overlapping[$id]) || $this->equal($edit, $previous)) {
				continue;
			}

			$after .= substr($code, $position, $edit->offset - $position) . $edit->text;
			$position = $edit->offset + $edit->length;
			$previous = $edit;
		}

		return $after . substr($code, $position);
	}

	private function equal(CodeEdit $edit, ?CodeEdit $other): bool
	{
		return $other !== null && $edit->offset === $other->offset && $edit->length === $other->length && $edit->text === $other->text;
	}

}
