<?php declare(strict_types = 1);

namespace Tally\Expectation;

use Tally\BenchmarkCase;
use Tally\Metrics;
use function array_values;
use function sprintf;

/**
 * Plans the new code of the files that keep the expectations of the accepted cases. Cases that
 * share a file are rewritten one after another into one change of it. ExpectationFiles reads and
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
		/** @var array<string, FileChange> $files */
		$files = [];
		$failures = [];
		foreach ($accepted as [$case, $measured]) {
			$id = $case->id->toString();
			$file = $case->expectations->file();
			$before = $code[$file] ?? false;
			if ($before === false) {
				$failures[$id] = sprintf('Cannot read %s.', $file);
				continue;
			}

			$change = $files[$file] ?? new FileChange($file, $before, $before, []);
			try {
				$after = $case->expectations->rewrite($change->after, $measured);
			} catch (ExpectationException $e) {
				$failures[$id] = $e->getMessage();
				continue;
			}
			$files[$file] = new FileChange($file, $before, $after, [...$change->cases, $id]);
		}

		return new ChangePlan(array_values($files), $failures);
	}

}
