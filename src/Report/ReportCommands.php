<?php declare(strict_types = 1);

namespace Tally\Report;

use Tally\Comparison\CaseComparison;
use function escapeshellarg;
use function sprintf;

/**
 * The commands a report suggests: one that runs the worst case again, and the ones that accept
 * the measured numbers, which in GitHub Actions download them from the run first.
 */
final class ReportCommands
{

	public const BINARY = 'vendor/bin/tally';

	/** Where the accept commands download or write the results */
	public const RESULTS_DIRECTORY = 'tmp/tally';

	/** The artifacts of a GitHub Actions run that hold the results */
	public const ARTIFACT_PATTERN = 'tally-*';

	public function reproduce(?CaseComparison $worst): string
	{
		return $worst === null ? '' : sprintf('%s run --filter=%s', self::BINARY, escapeshellarg($worst->id->toString()));
	}

	/**
	 * @param array<string, string> $environment
	 * @param string|null $resultsDirectory where the run wrote the results, null when it kept none
	 */
	public function accept(array $environment, ?string $resultsDirectory): string
	{
		$runId = $environment['GITHUB_RUN_ID'] ?? null;
		$repository = $environment['GITHUB_REPOSITORY'] ?? null;
		if (($environment['GITHUB_ACTIONS'] ?? null) === 'true' && $runId !== null && $repository !== null) {
			return sprintf(
				"gh run download %s -R %s -p '%s' -D %s\n%s accept %4\$s",
				$runId,
				$repository,
				self::ARTIFACT_PATTERN,
				self::RESULTS_DIRECTORY,
				self::BINARY,
			);
		}
		if ($resultsDirectory !== null) {
			return sprintf('%s accept %s', self::BINARY, escapeshellarg($resultsDirectory));
		}

		return sprintf("%s run --output=%s\n%1\$s accept %2\$s", self::BINARY, self::RESULTS_DIRECTORY);
	}

}
