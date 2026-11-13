<?php declare(strict_types = 1);

namespace Tally\Cli;

use Override;
use Tally\Comparison\Comparator;
use Tally\Comparison\Status;
use Throwable;
use function sprintf;

/**
 * Writes the measured numbers of the cases that changed as their expectations: into #[Expected]
 * attributes, or wherever a data provider keeps them. Cases within their tolerance keep their
 * numbers, so accepting does not churn them.
 */
final class AcceptCommand implements Command
{

	public function __construct(
		private SelectedCases $selectedCases,
		private ResultsArguments $resultsArguments,
		private Comparator $comparator
	)
	{
	}

	#[Override]
	public function name(): string
	{
		return 'accept';
	}

	#[Override]
	public function description(): string
	{
		return 'Makes the results of tally run --output the expected numbers of the changed cases';
	}

	#[Override]
	public function argumentsUsage(): string
	{
		return $this->resultsArguments->usage();
	}

	#[Override]
	public function options(): array
	{
		return [...$this->selectedCases->suiteOptions(), ...$this->selectedCases->selectionOptions()];
	}

	#[Override]
	public function execute(Input $input, Output $output): int
	{
		$measurements = $this->resultsArguments->read($input);
		$cases = $this->selectedCases->load($input, $this->selectedCases->configuration($input), $this->resultsArguments->paths($input));

		$accepted = 0;
		$failed = false;
		foreach ($cases as $case) {
			$measurement = $measurements[$case->id->toString()] ?? null;
			$status = $this->comparator->compareCase($case, $measurement)->status;
			if ($measurement === null || $status === Status::Ok || $status === Status::NotMeasured) {
				continue;
			}

			try {
				$case->expectations->write($measurement->metrics);
			} catch (Throwable $e) {
				$output->writeError(sprintf("Cannot accept %s: %s\n", $case->id->toString(), $e->getMessage()));
				$failed = true;
				continue;
			}
			$output->write(sprintf("%s  %s\n", $status->value, $case->id->toString()));
			$accepted++;
		}

		$output->write(sprintf("\nAccepted the numbers of %d %s.\n", $accepted, $accepted === 1 ? 'case' : 'cases'));

		return $failed ? Application::EXIT_ERROR : Application::EXIT_SUCCESS;
	}

}
