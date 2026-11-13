<?php declare(strict_types = 1);

namespace Tally\Cli;

use Override;
use RuntimeException;
use Tally\BenchmarkCase;
use Tally\Comparison\Comparator;
use Tally\Execution\CaseJobs;
use Tally\Execution\CodSpeedInstallation;
use Tally\Execution\DirectoryRemover;
use Tally\Execution\WorkerSettings;
use Tally\Measurement;
use Tally\Process\Job;
use Tally\Process\ProcessPool;
use Tally\Report\Report;
use Tally\Result\ResultsDirectory;
use Tally\Runner\CodSpeedException;
use function array_map;
use function count;
use function ctype_digit;
use function file_get_contents;
use function get_loaded_extensions;
use function getmypid;
use function max;
use function preg_match_all;
use function realpath;
use function sprintf;
use function sys_get_temp_dir;

/**
 * Measures the cases: each case runs natively first, then under CodSpeed's CPU simulation, which
 * counts it. Prints a character for each finished case and then the report.
 */
final class RunCommand implements Command
{

	/**
	 * @param array<string, string> $environment of tally, the workers get it too
	 */
	public function __construct(
		private SelectedCases $selectedCases,
		private CodSpeedInstallation $installation,
		private CaseJobs $caseJobs,
		private ProcessPool $pool,
		private Comparator $comparator,
		private ProgressFormat $progress,
		private ResultsDirectory $results,
		private ReportOutput $reportOutput,
		private DirectoryRemover $remover,
		private array $environment,
		private string $workingDirectory
	)
	{
	}

	#[Override]
	public function name(): string
	{
		return 'run';
	}

	#[Override]
	public function description(): string
	{
		return 'Counts the cases under CodSpeed and compares them with the expected numbers';
	}

	#[Override]
	public function argumentsUsage(): string
	{
		return '[<path>...]';
	}

	#[Override]
	public function options(): array
	{
		return [
			...$this->selectedCases->suiteOptions(),
			...$this->selectedCases->selectionOptions(),
			new Option('jobs', 'n', 'How many cases run at once, the number of CPUs by default'),
			new Option('output', 'directory', 'Keep the results there, for tally compare and tally accept'),
		];
	}

	#[Override]
	public function execute(Input $input, Output $output): int
	{
		$jobs = $this->jobs($input);
		try {
			[$codspeed, $instrumentHooks] = $this->installation->locate($this->environment, get_loaded_extensions());
		} catch (CodSpeedException $e) {
			$output->writeError($e->getMessage() . "\n");

			return Application::EXIT_ERROR;
		}

		$configuration = $this->selectedCases->configuration($input);
		$cases = $this->selectedCases->load($input, $configuration, $input->arguments());
		$workDirectory = sys_get_temp_dir() . '/tally-' . getmypid();
		$settings = new WorkerSettings(
			$configuration->iniEntries(),
			$this->suiteArguments($input),
			$codspeed,
			$instrumentHooks,
			$workDirectory,
			$configuration->nativeTimeLimitSeconds(),
			$configuration->countedTimeLimitSeconds(),
		);

		$output->write(sprintf(
			"Tally counts %d %s under CodSpeed's CPU simulation, %d at once.\n\n",
			count($cases),
			count($cases) === 1 ? 'case' : 'cases',
			$jobs,
		));
		try {
			$measurements = $this->measure($cases, $settings, $jobs, $input->option('output'), $output);
		} catch (RuntimeException $e) {
			$output->writeError($e->getMessage() . "\n");

			return Application::EXIT_ERROR;
		} finally {
			$this->remover->remove($workDirectory);
		}

		$report = new Report(array_map(fn (BenchmarkCase $case) => $this->comparator->compareCase($case, $measurements[$case->id->toString()] ?? null), $cases));
		if ($cases !== []) {
			$output->write("\n");
		}
		$this->reportOutput->write($report, $output, $this->environment, $this->workingDirectory, $input->option('output'));

		return $report->fails() ? Application::EXIT_FAILURE : Application::EXIT_SUCCESS;
	}

	/**
	 * @param list<BenchmarkCase> $cases
	 * @param positive-int $jobs
	 * @return array<string, Measurement> by case ID
	 * @throws RuntimeException
	 */
	private function measure(array $cases, WorkerSettings $settings, int $jobs, ?string $resultsDirectory, Output $output): array
	{
		$measurements = [];
		$this->pool->run(
			array_map(fn (BenchmarkCase $case): Job => $this->caseJobs->job($settings, $case), $cases),
			$jobs,
			$this->workingDirectory,
			$this->environment,
			function (Job $job, array $processResults) use ($settings, $resultsDirectory, $output, &$measurements, $cases): void {
				$case = $job->subject;
				$measurement = $this->caseJobs->measurement($settings, $case->id, $processResults);
				$measurements[$case->id->toString()] = $measurement;
				if ($resultsDirectory !== null) {
					$this->results->write($resultsDirectory, $measurement);
				}

				$status = $this->comparator->compareCase($case, $measurement)->status;
				$output->write($this->progress->after(count($measurements), count($cases), $status, $output->colors));
			},
		);

		return $measurements;
	}

	/**
	 * @return positive-int
	 * @throws UsageException
	 */
	private function jobs(Input $input): int
	{
		$jobs = $input->option('jobs');
		if ($jobs === null) {
			$cpuInfo = @file_get_contents('/proc/cpuinfo');

			return max(1, $cpuInfo === false ? 1 : (int) preg_match_all('~^processor\s~m', $cpuInfo));
		}
		if (!ctype_digit($jobs) || (int) $jobs < 1) {
			throw new UsageException(sprintf('Option --jobs needs a positive number, %s given.', $jobs));
		}

		return (int) $jobs;
	}

	/**
	 * The options and paths with which the workers load the same cases, with absolute paths, so
	 * they do not depend on the working directory.
	 *
	 * @return list<string>
	 */
	private function suiteArguments(Input $input): array
	{
		$arguments = [];
		$config = $input->option('config');
		if ($config !== null) {
			$arguments[] = '--config=' . $this->absolute($config);
		}
		$paths = $input->arguments();
		if ($paths === []) {
			return $arguments;
		}

		return [...$arguments, '--', ...array_map($this->absolute(...), $paths)];
	}

	private function absolute(string $path): string
	{
		$realPath = realpath($path);

		return $realPath === false ? $path : $realPath;
	}

}
