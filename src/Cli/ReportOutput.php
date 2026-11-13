<?php declare(strict_types = 1);

namespace Tally\Cli;

use Tally\BenchmarkCase;
use Tally\Expectation\ExpectationChanges;
use Tally\Expectation\ExpectationFiles;
use Tally\Measurement;
use Tally\Report\ConsoleReport;
use Tally\Report\ExpectationsPatch;
use Tally\Report\GitHubAnnotations;
use Tally\Report\MarkdownReport;
use Tally\Report\NewExpectations;
use Tally\Report\Report;
use Tally\Report\ReportCommands;
use function array_map;
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
		private ReportCommands $commands,
		private ExpectationFiles $files,
		private ExpectationChanges $changes,
		private ExpectationsPatch $patch
	)
	{
	}

	public function table(Report $report, Output $output): string
	{
		return $this->console->table($report, $output->colors);
	}

	/**
	 * @param list<BenchmarkCase> $cases the cases of the report
	 * @param array<string, Measurement> $measurements by case ID
	 * @param array<string, string> $environment
	 * @param string $workingDirectory patches point to files relative to it, and annotations outside GitHub Actions' workspace
	 * @param string|null $resultsDirectory where the results are, null when they were not kept
	 */
	public function write(Report $report, array $cases, array $measurements, Output $output, array $environment, string $workingDirectory, ?string $resultsDirectory): void
	{
		$reproduce = $this->commands->reproduce($report->worst());
		$accept = $this->commands->accept($environment, $resultsDirectory);
		$newExpectations = $this->newExpectations($cases, $measurements, $workingDirectory);

		$output->write($this->console->summary($report, $reproduce, $accept, $newExpectations, $output->colors));

		if (($environment['GITHUB_ACTIONS'] ?? null) !== 'true') {
			return;
		}

		// annotations point to files relative to the repository, also when tally runs in a subdirectory of it
		$output->write($this->annotations->render($report, $environment['GITHUB_WORKSPACE'] ?? $workingDirectory));
		$summary = $environment['GITHUB_STEP_SUMMARY'] ?? null;
		if ($summary === null || @file_put_contents($summary, $this->markdown->render($report, $reproduce, $accept, $newExpectations), FILE_APPEND) !== false) {
			return;
		}

		$output->error(sprintf('Cannot write the job summary to %s.', $summary));
	}

	/**
	 * The expectations of the measured cases that have none yet, so that a contributor who adds a
	 * benchmark can apply them without running CodSpeed.
	 *
	 * @param list<BenchmarkCase> $cases
	 * @param array<string, Measurement> $measurements
	 */
	private function newExpectations(array $cases, array $measurements, string $workingDirectory): NewExpectations
	{
		$accepted = [];
		foreach ($cases as $case) {
			$measurement = $measurements[$case->id->toString()] ?? null;
			if ($measurement === null || $measurement->failure !== null || $case->growth !== null || $case->expectations->read() !== null) {
				continue;
			}

			$accepted[] = [$case, $measurement->metrics];
		}

		$plan = $this->changes->plan($accepted, $this->files->read(array_map(static fn (array $accepted): BenchmarkCase => $accepted[0], $accepted)));

		return new NewExpectations($this->patch->render($plan, $workingDirectory), $plan->failures);
	}

}
