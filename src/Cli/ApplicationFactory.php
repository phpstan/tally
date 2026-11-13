<?php declare(strict_types = 1);

namespace Tally\Cli;

use Tally\Comparison\Comparator;
use Tally\Discovery\CaseExpander;
use Tally\Discovery\DirectorySuiteLoader;
use Tally\Execution\CaseJobs;
use Tally\Execution\CaseOutcome;
use Tally\Execution\CodSpeedInstallation;
use Tally\Execution\DirectoryRemover;
use Tally\Execution\WorkerCommandLine;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Process\ProcessKiller;
use Tally\Process\ProcessPool;
use Tally\Process\ProcessTable;
use Tally\Report\ConsoleReport;
use Tally\Report\GitHubAnnotations;
use Tally\Report\MarkdownReport;
use Tally\Report\ReportCommands;
use Tally\Report\Rows;
use Tally\Report\TextTable;
use Tally\Result\CallgrindProfile;
use Tally\Result\MeasurementJson;
use Tally\Result\ResultsDirectory;
use Tally\Runner\ArgumentResolver;
use Tally\Runner\CaseRunner;
use Tally\Runner\IdleCounter;
use Tally\Runner\PhpMemory;

/**
 * Builds the application with its services. The only place that decides which implementation
 * of a service the others get.
 */
final class ApplicationFactory
{

	/**
	 * @param resource $stdout
	 * @param resource $stderr
	 * @param string $workingDirectory where tally.php is looked for
	 * @param array<string, string> $environment
	 * @param bool $colors whether to color the standard output
	 * @param string $phpBinary runs the workers
	 * @param string $tallyBinary the bin/tally the workers run
	 */
	public function create($stdout, $stderr, string $workingDirectory, array $environment, bool $colors, string $phpBinary, string $tallyBinary): Application
	{
		$selectedCases = new SelectedCases(new DirectorySuiteLoader(new CaseExpander(new ExpectedAttributeRewriter())), $workingDirectory);
		$idle = new IdleCounter();
		$json = new MeasurementJson();
		$comparator = new Comparator();
		$rows = new Rows();
		$resultsArguments = new ResultsArguments(new ResultsDirectory($json));
		$reportOutput = new ReportOutput(new ConsoleReport(new TextTable(), $rows), new MarkdownReport($rows), new GitHubAnnotations($rows), new ReportCommands());

		return new Application(
			new Output($stdout, $stderr, $colors),
			new ArgumentParser(),
			[
				new ListCommand($selectedCases),
				new RunCommand(
					$selectedCases,
					new CodSpeedInstallation(),
					new CaseJobs(new WorkerCommandLine($phpBinary, $tallyBinary), new CaseOutcome(), new CallgrindProfile(), $json),
					new ProcessPool(new ProcessKiller(new ProcessTable())),
					$comparator,
					new ProgressFormat(),
					new ResultsDirectory($json),
					$reportOutput,
					new DirectoryRemover(),
					$environment,
					$workingDirectory,
				),
				new CompareCommand($selectedCases, $resultsArguments, $comparator, $reportOutput, $environment, $workingDirectory),
				new AcceptCommand($selectedCases, $resultsArguments, $comparator),
			],
			[new WorkerCommand($selectedCases, new CaseRunner(new ArgumentResolver(), new PhpMemory(), $idle), $idle, $json)],
		);
	}

}
