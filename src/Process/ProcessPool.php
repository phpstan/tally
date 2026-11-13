<?php declare(strict_types = 1);

namespace Tally\Process;

use Closure;
use RuntimeException;
use function array_shift;
use function count;
use function hrtime;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function sprintf;
use function usleep;

/**
 * Runs jobs in parallel processes. The steps of a job run one after another while each succeeds;
 * a step that runs longer than its time limit is killed with all its descendants.
 */
final class ProcessPool
{

	/** How often the pool checks whether its processes have ended */
	private const POLL_MICROSECONDS = 10_000;

	public function __construct(private ProcessKiller $killer)
	{
	}

	/**
	 * @template T
	 * @param list<Job<T>> $jobs started in this order
	 * @param positive-int $parallel how many jobs run at once
	 * @param array<string, string> $environment the environment variables of the processes
	 * @param Closure(Job<T>, non-empty-list<ProcessResult>): void $finished called when a job ends, with the result of
	 *     each step that ran
	 * @throws RuntimeException when a process cannot be started
	 */
	public function run(array $jobs, int $parallel, string $workingDirectory, array $environment, Closure $finished): void
	{
		/** @var array<int, array{Job<T>, list<ProcessResult>, StartedProcess}> $running */
		$running = [];
		$next = 0;
		while ($jobs !== [] || $running !== []) {
			while ($jobs !== [] && count($running) < $parallel) {
				$job = array_shift($jobs);
				$running[$next++] = [$job, [], $this->start($job->steps[0], $workingDirectory, $environment)];
			}

			usleep(self::POLL_MICROSECONDS);
			foreach ($running as $key => [$job, $results, $process]) {
				$result = $this->poll($process);
				if ($result === null) {
					continue;
				}

				$results[] = $result;
				$step = $job->steps[count($results)] ?? null;
				if ($result->succeeded() && $step !== null) {
					$running[$key] = [$job, $results, $this->start($step, $workingDirectory, $environment)];
					continue;
				}

				unset($running[$key]);
				$finished($job, $results);
			}
		}
	}

	/**
	 * @param array<string, string> $environment
	 * @throws RuntimeException
	 */
	private function start(ProcessCommand $command, string $workingDirectory, array $environment): StartedProcess
	{
		// fails when the log file cannot be opened
		$process = @proc_open(
			$command->arguments,
			// appending, the two outputs do not overwrite each other
			[0 => ['file', '/dev/null', 'r'], 1 => ['file', $command->logFile, 'a'], 2 => ['file', $command->logFile, 'a']],
			$pipes,
			$workingDirectory,
			$environment,
		);
		if ($process === false) {
			throw new RuntimeException(sprintf('Cannot start %s with its output in %s.', $command->arguments[0], $command->logFile));
		}

		return new StartedProcess($command, $process, hrtime(true) / 1e9);
	}

	private function poll(StartedProcess $process): ?ProcessResult
	{
		$status = proc_get_status($process->process);
		if ($status['running']) {
			if (hrtime(true) / 1e9 - $process->startedAt <= $process->command->timeLimitSeconds) {
				return null;
			}

			$this->killer->kill($status['pid']);
			proc_close($process->process);

			return ProcessResult::timedOut($process->command);
		}

		proc_close($process->process);
		$exitCode = $status['signaled'] ? ProcessResult::SIGNAL_EXIT_CODE_OFFSET + $status['termsig'] : $status['exitcode'];

		return ProcessResult::exited($process->command, $exitCode);
	}

}
