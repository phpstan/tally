<?php declare(strict_types = 1);

namespace Tally\Cli;

use Override;
use RuntimeException;
use Tally\BenchmarkCase;
use Tally\Comparison\CaseComparison;
use Tally\Comparison\Comparator;
use Tally\Execution\CaseJobs;
use Tally\Execution\CodSpeedInstallation;
use Tally\Execution\DirectoryRemover;
use Tally\Execution\WorkerSettings;
use Tally\Expectation\SourceLocation;
use Tally\Measurement;
use Tally\Process\Job;
use Tally\Process\ProcessPool;
use Tally\Report\Report;
use Tally\Result\ResultsDirectory;
use Tally\Runner\CodSpeedException;
use function array_flip;
use function array_intersect_key;
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
use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;
use const ZEND_DEBUG_BUILD;
use const ZEND_THREAD_SAFE;

/**
 * Measures the cases: each case runs natively first, then under CodSpeed's CPU simulation, which
 * counts it. Prints a character for each finished case and then the report.
 */
final class RunCommand implements Command
{

	/**
	 * @param array<string, string> $environment of tally, the workers get the variables the configuration keeps
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
			[$valgrind, $valgrindTools, $extension] = $this->installation->locate(get_loaded_extensions(), self::phpBuild());
		} catch (CodSpeedException $e) {
			$output->error($e->getMessage());

			return Application::EXIT_ERROR;
		}

		$configuration = $this->selectedCases->configuration($input);
		$cases = $this->selectedCases->load($input, $configuration, $input->arguments());
		if ($cases === []) {
			$output->write("This shard has no cases to count.\n");

			return Application::EXIT_SUCCESS;
		}
		// before counting, which takes minutes: an expectation tally cannot find fails now
		$located = [];
		foreach ($cases as $case) {
			$located[] = [$case, $case->expectations->location()];
		}
		$workDirectory = sys_get_temp_dir() . '/tally-' . getmypid();
		$settings = new WorkerSettings(
			$configuration->iniEntries(),
			$this->suiteArguments($input),
			$valgrind,
			$extension,
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
			$environment = [...array_intersect_key($this->environment, array_flip($configuration->environmentVariables())), 'VALGRIND_LIB' => $valgrindTools];
			$measurements = $this->measure($located, $settings, $environment, $jobs, $input->option('output'), $output);
		} catch (RuntimeException $e) {
			$output->error($e->getMessage());

			return Application::EXIT_ERROR;
		} finally {
			$this->remover->remove($workDirectory);
		}

		$report = new Report(array_map(
			fn (array $case): CaseComparison => $this->comparator->compareLocated($case[0], $case[1], $measurements),
			$located,
		));
		$output->write("\n");
		$this->reportOutput->write($report, $cases, $measurements, $output, $this->environment, $this->workingDirectory, $input->option('output'), $input->option('shard') !== null);

		return $report->fails() ? Application::EXIT_FAILURE : Application::EXIT_SUCCESS;
	}

	/**
	 * @param list<array{BenchmarkCase, SourceLocation}> $cases
	 * @param array<string, string> $environment of the workers
	 * @param positive-int $jobs
	 * @return array<string, Measurement> by case ID
	 * @throws RuntimeException
	 */
	private function measure(array $cases, WorkerSettings $settings, array $environment, int $jobs, ?string $resultsDirectory, Output $output): array
	{
		$jobsOfCases = [];
		foreach ($cases as $case) {
			$jobsOfCases[] = new Job($case, $this->caseJobs->steps($settings, $case[0]->id));
		}

		$measurements = [];
		$shown = 0;
		// the cases whose status is not shown yet, because their growth is compared with a base that did not finish
		$waiting = [];
		$this->pool->run(
			$jobsOfCases,
			$jobs,
			$this->workingDirectory,
			$environment,
			function (Job $job, array $processResults) use ($settings, $resultsDirectory, $output, &$measurements, &$shown, &$waiting, $cases): void {
				[$case] = $job->subject;
				$waiting[] = $job->subject;
				$measurement = $this->caseJobs->measurement($settings, $case->id, $processResults);
				$measurements[$case->id->toString()] = $measurement;
				if ($resultsDirectory !== null) {
					$this->results->write($resultsDirectory, $measurement);
				}

				$stillWaiting = [];
				foreach ($waiting as [$waitingCase, $location]) {
					$base = $waitingCase->growth?->base->toString();
					if ($base !== null && !isset($measurements[$base])) {
						$stillWaiting[] = [$waitingCase, $location];
						continue;
					}

					$shown++;
					$status = $this->comparator->compareLocated($waitingCase, $location, $measurements)->status;
					$output->write($this->progress->after($shown, count($cases), $status, $output->colors));
				}
				$waiting = $stillWaiting;
			},
		);

		return $measurements;
	}

	/**
	 * Which build of the tally_hooks extension this PHP loads: one per minor version, thread safe or
	 * not. Debug builds need one of their own, which releases do not include.
	 */
	private static function phpBuild(): string
	{
		return sprintf('%d.%d%s%s', PHP_MAJOR_VERSION, PHP_MINOR_VERSION, ZEND_THREAD_SAFE ? '-zts' : '', ZEND_DEBUG_BUILD ? '-debug' : '');
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
