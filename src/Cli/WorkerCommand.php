<?php declare(strict_types = 1);

namespace Tally\Cli;

use Override;
use Tally\BenchmarkCase;
use Tally\Measurement;
use Tally\Result\MeasurementJson;
use Tally\Runner\CaseRunner;
use Tally\Runner\CodSpeedCounter;
use Tally\Runner\FailureDescription;
use Tally\Runner\FfiInstrumentHooks;
use Tally\Runner\IdleCounter;
use Tally\ScratchDirectory;
use Throwable;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sprintf;

/**
 * Runs one case in this process and writes its measurement. tally run starts a worker natively,
 * and then under CodSpeed's CPU simulation with the instrument-hooks library, which counts it.
 */
final class WorkerCommand implements Command
{

	public function __construct(
		private SelectedCases $selectedCases,
		private CaseRunner $runner,
		private IdleCounter $idle,
		private MeasurementJson $json,
		private FailureDescription $failureDescription,
		private string $tallyDirectory,
		private string $workingDirectory
	)
	{
	}

	#[Override]
	public function name(): string
	{
		return 'worker';
	}

	#[Override]
	public function description(): string
	{
		return 'Runs one case in this process, tally run starts it for every case';
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
			new Option('case', 'id', 'The ID of the case to run'),
			new Option('scratch', 'directory', 'The scratch directory of the case, created when it does not exist'),
			new Option('result', 'file', 'Where to write the measurement as JSON'),
			new Option('instrument-hooks', 'library', "CodSpeed's instrument-hooks library, which counts the case under CodSpeed's CPU simulation"),
		];
	}

	#[Override]
	public function execute(Input $input, Output $output): int
	{
		$id = $this->required($input, 'case');
		$scratch = $this->required($input, 'scratch');
		$resultFile = $this->required($input, 'result');
		$case = $this->find($this->selectedCases->load($input, $this->selectedCases->configuration($input), $input->arguments()), $id);

		$measurement = $this->run($case, $scratch, $input->option('instrument-hooks'));
		if (@file_put_contents($resultFile, $this->json->encode($measurement)) === false) {
			$output->error(sprintf('Cannot write %s.', $resultFile));

			return Application::EXIT_ERROR;
		}

		return $measurement->failure === null ? Application::EXIT_SUCCESS : Application::EXIT_FAILURE;
	}

	private function run(BenchmarkCase $case, string $scratch, ?string $instrumentHooks): Measurement
	{
		try {
			if (!is_dir($scratch)) {
				mkdir($scratch, 0777, true);
			}
			$counter = $instrumentHooks === null ? $this->idle : new CodSpeedCounter(FfiInstrumentHooks::load($instrumentHooks));

			return Measurement::measured($case->id, $this->runner->run($case, new ScratchDirectory($scratch), $counter));
		} catch (Throwable $e) {
			return Measurement::failed($case->id, $this->failureDescription->describe($e, $this->tallyDirectory, $this->workingDirectory));
		}
	}

	/**
	 * @throws UsageException
	 */
	private function required(Input $input, string $option): string
	{
		$value = $input->option($option);
		if ($value === null) {
			throw new UsageException(sprintf('Option --%s is required.', $option));
		}

		return $value;
	}

	/**
	 * @param list<BenchmarkCase> $cases
	 * @throws UsageException
	 */
	private function find(array $cases, string $id): BenchmarkCase
	{
		foreach ($cases as $case) {
			if ($case->id->toString() === $id) {
				return $case;
			}
		}

		throw new UsageException(sprintf('There is no case %s.', $id));
	}

}
