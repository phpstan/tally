<?php declare(strict_types = 1);

namespace Tally\Cli;

use Override;
use RuntimeException;
use Tally\BenchmarkCase;
use Tally\Comparison\Comparator;
use Tally\Comparison\Status;
use Tally\Expectation\ExpectationChanges;
use Tally\Expectation\ExpectationFiles;
use function array_filter;
use function array_map;
use function count;
use function implode;
use function in_array;
use function sprintf;
use function str_pad;

/**
 * Writes the measured numbers of the cases that changed as their expectations: into #[Expected]
 * attributes, or wherever a data provider keeps them. Cases within their tolerance keep their
 * numbers, so accepting does not churn them.
 */
final class AcceptCommand implements Command
{

	/** The longest status of a case whose numbers are accepted: slower, faster, or new */
	private const STATUS_WIDTH = 6;

	public function __construct(
		private SelectedCases $selectedCases,
		private ResultsArguments $resultsArguments,
		private Comparator $comparator,
		private ExpectationFiles $files,
		private ExpectationChanges $changes
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

		$changed = [];
		foreach ($cases as $case) {
			$measurement = $measurements[$case->id->toString()] ?? null;
			$status = $this->comparator->compareCase($case, $measurement)->status;
			if ($measurement === null || $status === Status::Ok || $status === Status::NotMeasured) {
				continue;
			}

			$changed[] = [$case, $measurement->metrics, $status];
		}

		$plan = $this->changes->plan(
			array_map(static fn (array $change): array => [$change[0], $change[1]], $changed),
			$this->files->read(array_map(static fn (array $change): BenchmarkCase => $change[0], $changed)),
		);
		$failed = $plan->failures !== [];
		foreach ($plan->failures as $id => $failure) {
			$output->error(sprintf('Cannot accept %s: %s', $id, $failure));
		}
		$written = [];
		foreach ($plan->files as $change) {
			try {
				$this->files->write($change);
			} catch (RuntimeException $e) {
				$output->error(sprintf('Cannot accept %s: %s', implode(', ', $change->cases), $e->getMessage()));
				$failed = true;
				continue;
			}
			$written = [...$written, ...$change->cases];
		}

		$accepted = array_filter($changed, static fn (array $change): bool => in_array($change[0]->id->toString(), $written, true));
		foreach ($accepted as [$case, , $status]) {
			$output->write(sprintf("%s  %s\n", str_pad($status->value, self::STATUS_WIDTH), $case->id->toString()));
		}
		$output->write(sprintf(
			"%sAccepted the numbers of %d %s.\n",
			$accepted === [] ? '' : "\n",
			count($accepted),
			count($accepted) === 1 ? 'case' : 'cases',
		));

		return $failed ? Application::EXIT_ERROR : Application::EXIT_SUCCESS;
	}

}
