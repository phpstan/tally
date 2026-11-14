<?php declare(strict_types = 1);

namespace Tally\Cli;

use Override;
use RuntimeException;
use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Comparison\Comparator;
use Tally\Comparison\Status;
use Tally\Complexity\ComplexityClass;
use Tally\Complexity\GrowthPoint;
use Tally\Complexity\PresetFit;
use Tally\Expectation\ComplexityAttribute;
use Tally\Expectation\ExpectationChanges;
use Tally\Expectation\ExpectationFiles;
use Tally\Measurement;
use Tally\Metric;
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
 * numbers, so accepting does not churn them. A preset complexity class, like #[Linear], is
 * replaced by the preset that bounds the measured growth most tightly.
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
		private ExpectationChanges $changes,
		private PresetFit $fit
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
		$growing = [];
		foreach ($cases as $case) {
			$measurement = $measurements[$case->id->toString()] ?? null;
			$comparison = $this->comparator->compareCase($case, $measurements);
			if ($case->growth !== null) {
				$growing[$case->growth->base->toString()][] = [$case, $case->growth, $comparison->status, $measurement];
				continue;
			}
			$status = $comparison->status;
			if ($measurement === null || $status === Status::Ok || $status === Status::NotMeasured) {
				continue;
			}

			$changed[] = [$case, $measurement->metrics, $status];
		}

		$failed = false;
		$complexities = [];
		foreach ($growing as $methodCases) {
			$change = $this->complexityChange($methodCases);
			if ($change === null) {
				continue;
			}
			if ($change[2] === null) {
				$output->error(sprintf('Cannot accept %s: no preset complexity class bounds how its instructions grow, write a #[Complexity] that does.', $change[0]->toString()));
				$failed = true;
				continue;
			}

			$complexities[] = $change;
		}

		$plan = $this->changes->plan(
			array_map(static fn (array $change): array => [$change[0], $change[1]], $changed),
			array_map(static fn (array $change): array => [$change[0], $change[1], $change[2]], $complexities),
			$this->files->read([
				...array_map(static fn (array $change): string => $change[0]->expectations->file(), $changed),
				...array_map(static fn (array $change): string => $change[1]->file(), $complexities),
			]),
		);
		$failed = $failed || $plan->failures !== [];
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
		$acceptedComplexities = array_filter($complexities, static fn (array $change): bool => in_array($change[0]->toString(), $written, true));
		foreach ($acceptedComplexities as [$id, $attribute, $replacement, $status]) {
			$output->write(sprintf(
				"%s  %s: %s to %s\n",
				str_pad($status->value, self::STATUS_WIDTH),
				$id->toString(),
				$attribute->complexity->describe(),
				$replacement->describe(),
			));
		}
		$what = [];
		if ($accepted !== [] || $acceptedComplexities === []) {
			$what[] = sprintf('the numbers of %d %s', count($accepted), count($accepted) === 1 ? 'case' : 'cases');
		}
		if ($acceptedComplexities !== []) {
			$what[] = count($acceptedComplexities) === 1 ? 'the complexity class of 1 benchmark' : sprintf('the complexity classes of %d benchmarks', count($acceptedComplexities));
		}
		$output->write(sprintf("%sAccepted %s.\n", $accepted === [] && $acceptedComplexities === [] ? '' : "\n", implode(' and ', $what)));

		return $failed ? Application::EXIT_ERROR : Application::EXIT_SUCCESS;
	}

	/**
	 * The preset that replaces the complexity class of a benchmark method, when the instructions of
	 * all its cases were counted: slower when its class allows less than they grew, faster when a
	 * tighter preset bounds them.
	 *
	 * @param non-empty-list<array{BenchmarkCase, GrowthPoint, Status, Measurement|null}> $cases
	 * @return array{CaseId, ComplexityAttribute, ComplexityClass|null, Status}|null null when it keeps its class, the class null when no preset bounds them
	 */
	private function complexityChange(array $cases): ?array
	{
		$points = [];
		$status = Status::Faster;
		foreach ($cases as [, $growth, $caseStatus, $measurement]) {
			$instructions = $measurement?->metrics->get(Metric::Instructions);
			if ($instructions === null) {
				return null;
			}
			$points[] = [$growth->size, $instructions];
			if ($caseStatus !== Status::Slower) {
				continue;
			}

			$status = Status::Slower;
		}

		[$case, $growth] = $cases[0];
		$attribute = $growth->attribute;
		$fitted = $this->fit->fit($attribute->complexity, $points, $case->tolerance);
		if ($fitted === $attribute->complexity) {
			return null;
		}

		return [new CaseId($case->id->class, $case->id->method, null), $attribute, $fitted, $status];
	}

}
