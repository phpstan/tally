<?php declare(strict_types = 1);

namespace Tally\Execution;

use RuntimeException;
use Tally\CaseId;
use Tally\Measurement;
use Tally\Process\ProcessCommand;
use Tally\Process\ProcessResult;
use Tally\Result\CallgrindProfile;
use Tally\Result\InvalidResultException;
use Tally\Result\MeasurementJson;
use function file_get_contents;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;
use function sprintf;

/**
 * The steps that measure a case, and its measurement from the files the job left behind. Each case
 * gets a directory in the work directory: its scratch directory, which both runs share, and the
 * results, logs, and profiles of the runs.
 */
final class CaseJobs
{

	public function __construct(
		private WorkerCommandLine $commandLine,
		private CaseOutcome $outcome,
		private CallgrindProfile $profile,
		private MeasurementJson $json
	)
	{
	}

	/**
	 * @return non-empty-list<ProcessCommand>
	 * @throws RuntimeException when the case's directories cannot be created
	 */
	public function steps(WorkerSettings $settings, CaseId $id): array
	{
		$directory = $this->directory($settings, $id);
		foreach (['/scratch', '/profile'] as $subdirectory) {
			if (!is_dir($directory . $subdirectory) && !@mkdir($directory . $subdirectory, 0777, true)) {
				throw new RuntimeException(sprintf('Cannot create %s.', $directory . $subdirectory));
			}
		}

		return [
			new ProcessCommand(
				$this->commandLine->native($settings, $id, $directory . '/scratch', $directory . '/native.json'),
				$directory . '/native.log',
				$settings->nativeTimeLimitSeconds,
			),
			new ProcessCommand(
				$this->commandLine->counted($settings, $id, $directory . '/scratch', $directory . '/counted.json', $directory . '/profile'),
				$directory . '/counted.log',
				$settings->countedTimeLimitSeconds,
			),
		];
	}

	/**
	 * @param non-empty-list<ProcessResult> $results of the job's steps that ran
	 */
	public function measurement(WorkerSettings $settings, CaseId $id, array $results): Measurement
	{
		$directory = $this->directory($settings, $id);
		$runs = [$this->run($id, 'The native run', $results[0], $directory . '/native.json')];
		if (isset($results[1])) {
			$runs[] = $this->run($id, 'CodSpeed', $results[1], $directory . '/counted.json');
		}

		$parts = [];
		$profiles = glob($directory . '/profile/*.out');
		foreach ($profiles === false ? [] : $profiles as $profile) {
			$parts = [...$parts, ...$this->profile->parts((string) file_get_contents($profile))];
		}

		return $this->outcome->measurement($id, $runs, $parts);
	}

	private function run(CaseId $id, string $name, ProcessResult $result, string $resultFile): WorkerRun
	{
		$written = null;
		if (is_file($resultFile)) {
			try {
				$written = $this->json->decode((string) file_get_contents($resultFile));
			} catch (InvalidResultException $e) {
				$written = Measurement::failed($id, sprintf('%s wrote an invalid result: %s', $name, $e->getMessage()));
			}
		}

		return new WorkerRun($name, $result, $written, (string) @file_get_contents($result->command->logFile));
	}

	private function directory(WorkerSettings $settings, CaseId $id): string
	{
		return $settings->workDirectory . '/' . $id->fileName();
	}

}
