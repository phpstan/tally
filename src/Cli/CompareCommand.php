<?php declare(strict_types = 1);

namespace Tally\Cli;

use Override;
use Tally\BenchmarkCase;
use Tally\Comparison\Comparator;
use Tally\Report\Report;
use function array_map;

/**
 * Compares the results of an earlier run with the expected numbers again, such as the results
 * of all shards of a CI run.
 */
final class CompareCommand implements Command
{

	/**
	 * @param array<string, string> $environment
	 */
	public function __construct(
		private SelectedCases $selectedCases,
		private ResultsArguments $resultsArguments,
		private Comparator $comparator,
		private ReportOutput $reportOutput,
		private array $environment,
		private string $workingDirectory
	)
	{
	}

	#[Override]
	public function name(): string
	{
		return 'compare';
	}

	#[Override]
	public function description(): string
	{
		return 'Compares the results of tally run --output with the expected numbers again';
	}

	#[Override]
	public function argumentsUsage(): string
	{
		return $this->resultsArguments->usage();
	}

	#[Override]
	public function options(): array
	{
		return [
			...$this->selectedCases->suiteOptions(),
			...$this->selectedCases->selectionOptions(),
			new Option('table', null, 'Also print every case with its metrics and status'),
		];
	}

	#[Override]
	public function execute(Input $input, Output $output): int
	{
		$measurements = $this->resultsArguments->read($input);
		$cases = $this->selectedCases->load($input, $this->selectedCases->configuration($input), $this->resultsArguments->paths($input));

		$report = new Report(array_map(fn (BenchmarkCase $case) => $this->comparator->compareCase($case, $measurements[$case->id->toString()] ?? null), $cases));
		if ($input->flag('table')) {
			$output->write($this->reportOutput->table($report, $output) . "\n");
		}
		$this->reportOutput->write($report, $output, $this->environment, $this->workingDirectory, $this->resultsArguments->directory($input));

		return $report->fails() ? Application::EXIT_FAILURE : Application::EXIT_SUCCESS;
	}

}
