<?php declare(strict_types = 1);

namespace Tally\Cli;

use Tally\Report\ConsoleReport;
use Tally\Report\GitHubAnnotations;
use Tally\Report\MarkdownReport;
use Tally\Report\Report;
use Tally\Report\ReportCommands;
use function file_put_contents;
use function sprintf;
use const FILE_APPEND;

/**
 * Writes the report of tally run and tally compare: the errors of the cases that were not
 * measured, and the summary. In GitHub Actions also the annotations, and the markdown report
 * into the job summary.
 */
final class ReportOutput
{

	public function __construct(
		private ConsoleReport $console,
		private MarkdownReport $markdown,
		private GitHubAnnotations $annotations,
		private ReportCommands $commands
	)
	{
	}

	public function table(Report $report, Output $output): string
	{
		return $this->console->table($report, $output->colors);
	}

	/**
	 * @param array<string, string> $environment
	 * @param string $workingDirectory annotations point to files relative to it
	 * @param string|null $resultsDirectory where the results are, null when they were not kept
	 */
	public function write(Report $report, Output $output, array $environment, string $workingDirectory, ?string $resultsDirectory): void
	{
		$reproduce = $this->commands->reproduce($report->worst());
		$accept = $this->commands->accept($environment, $resultsDirectory);

		$failures = $this->console->failures($report, $output->colors);
		if ($failures !== '') {
			$output->write($failures . "\n");
		}
		$output->write($this->console->summary($report, $reproduce, $accept, $output->colors));

		if (($environment['GITHUB_ACTIONS'] ?? null) !== 'true') {
			return;
		}

		$output->write($this->annotations->render($report, $workingDirectory));
		$summary = $environment['GITHUB_STEP_SUMMARY'] ?? null;
		if ($summary === null || @file_put_contents($summary, $this->markdown->render($report, $reproduce, $accept), FILE_APPEND) !== false) {
			return;
		}

		$output->writeError(sprintf("Cannot write the job summary to %s.\n", $summary));
	}

}
