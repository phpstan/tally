<?php declare(strict_types = 1);

namespace Tally\Expectation;

use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Complexity\ComplexityClass;
use Tally\Metrics;
use function array_values;
use function sprintf;

/**
 * Plans the new code of the files that keep the expectations of the accepted cases, and the
 * complexity classes of the accepted benchmark methods. Changes of one file are made one after
 * another into one change of it. ExpectationFiles reads and writes the files.
 */
final class ExpectationChanges
{

	/**
	 * @param list<array{BenchmarkCase, Metrics}> $accepted the cases with their measured metrics
	 * @param list<array{CaseId, ComplexityAttribute, ComplexityClass}> $complexities the benchmark methods with the classes that replace theirs
	 * @param array<string, string|false> $code the code of the files by path, false for a file that cannot be read
	 */
	public function plan(array $accepted, array $complexities, array $code): ChangePlan
	{
		$changes = [];
		foreach ($accepted as [$case, $measured]) {
			$changes[] = [$case->id, $case->expectations->file(), static fn (string $code): string => $case->expectations->rewrite($code, $measured)];
		}
		foreach ($complexities as [$id, $attribute, $replacement]) {
			$changes[] = [$id, $attribute->file(), static fn (string $code): string => $attribute->rewrite($code, $replacement)];
		}

		/** @var array<string, FileChange> $files */
		$files = [];
		$failures = [];
		foreach ($changes as [$caseId, $file, $rewrite]) {
			$id = $caseId->toString();
			$before = $code[$file] ?? false;
			if ($before === false) {
				$failures[$id] = sprintf('Cannot read %s.', $file);
				continue;
			}

			$change = $files[$file] ?? new FileChange($file, $before, $before, []);
			try {
				$after = $rewrite($change->after);
			} catch (ExpectationException $e) {
				$failures[$id] = $e->getMessage();
				continue;
			}
			$files[$file] = new FileChange($file, $before, $after, [...$change->cases, $id]);
		}

		return new ChangePlan(array_values($files), $failures);
	}

}
