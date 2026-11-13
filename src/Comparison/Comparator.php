<?php declare(strict_types = 1);

namespace Tally\Comparison;

use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Complexity\GrowthPoint;
use Tally\Complexity\GrowthTolerance;
use Tally\Expectation\ExpectationException;
use Tally\Expectation\SourceLocation;
use Tally\Measurement;
use Tally\Metric;
use Tally\Metrics;
use function array_map;
use function round;
use function sprintf;

/**
 * Compares the measured metrics of a case with the expected ones. A metric the run did not
 * count, like instructions in a run without CodSpeed, is left out. Nothing expected of a
 * metric that is measured above zero is slower, because no share of zero allows anything.
 */
final class Comparator
{

	public const NOT_RUN = 'It did not run.';

	/**
	 * @param array<string, Measurement> $measurements of the run, by case ID
	 * @throws ExpectationException
	 */
	public function compareCase(BenchmarkCase $case, array $measurements): CaseComparison
	{
		return $this->compareLocated($case, $case->expectations->location(), $measurements);
	}

	/**
	 * Like compareCase(), when the location is known. A case compared by its growth is compared
	 * with the measurement of its base.
	 *
	 * @param array<string, Measurement> $measurements of the run, by case ID
	 */
	public function compareLocated(BenchmarkCase $case, SourceLocation $location, array $measurements): CaseComparison
	{
		$measurement = $measurements[$case->id->toString()] ?? null;
		if ($case->growth === null) {
			return $this->compare($case->id, $location, $case->expectations->read(), $case->tolerance, $measurement);
		}

		return $this->compareGrowth($case->id, $location, $case->growth, $case->tolerance, $measurement, $measurements[$case->growth->base->toString()] ?? null);
	}

	/**
	 * Expects the instructions of the base times the growth, and reports only more than that, as
	 * the complexity class is an upper bound.
	 *
	 * @param Measurement|null $measurement null when the case did not run
	 * @param Measurement|null $base of the data set the growth starts from, null when it did not run
	 */
	public function compareGrowth(CaseId $id, SourceLocation $location, GrowthPoint $growth, TolerancePolicy $tolerance, ?Measurement $measurement, ?Measurement $base): CaseComparison
	{
		$tolerance = new GrowthTolerance($growth->bound, $tolerance);
		if ($measurement === null || $measurement->failure !== null) {
			return new CaseComparison($id, $location, $tolerance, [], $measurement->failure ?? self::NOT_RUN, Status::NotMeasured, false);
		}
		$measured = $measurement->metrics->get(Metric::Instructions);
		if ($measured === null) {
			return new CaseComparison($id, $location, $tolerance, [], 'It did not count instructions.', Status::NotMeasured, false);
		}
		if ($growth->base->toString() === $id->toString()) {
			return new CaseComparison($id, $location, $tolerance, [new MetricComparison(Metric::Instructions, null, $measured, null, Status::Ok)], null, Status::Ok, false);
		}

		$baseInstructions = $base?->metrics->get(Metric::Instructions);
		if ($baseInstructions === null) {
			return new CaseComparison($id, $location, $tolerance, [], sprintf('Its growth is compared with %s, which was not measured.', $growth->base->toString()), Status::NotMeasured, false);
		}

		$metric = $this->compareMetric(Metric::Instructions, (int) round($baseInstructions * $growth->growth), $measured, $tolerance);
		if ($metric->status === Status::Faster) {
			$metric = new MetricComparison($metric->metric, $metric->expected, $metric->measured, $metric->allowedChange, Status::Ok);
		}

		return new CaseComparison($id, $location, $tolerance, [$metric], null, $metric->status, false);
	}

	public function compare(CaseId $id, SourceLocation $location, ?Metrics $expected, TolerancePolicy $tolerance, ?Measurement $measurement): CaseComparison
	{
		if ($measurement === null || $measurement->failure !== null) {
			$metrics = [];
			foreach ($expected?->toArray() ?? [] as $metric => $value) {
				$metrics[] = new MetricComparison(Metric::from($metric), $value, null, null, Status::NotMeasured);
			}

			return new CaseComparison($id, $location, $tolerance, $metrics, $measurement->failure ?? self::NOT_RUN, Status::NotMeasured, true);
		}

		$metrics = [];
		foreach ($measurement->metrics->toArray() as $name => $measured) {
			$metric = Metric::from($name);
			$metrics[] = $this->compareMetric($metric, $expected?->get($metric), $measured, $tolerance);
		}

		return new CaseComparison(
			$id,
			$location,
			$tolerance,
			$metrics,
			null,
			Status::worst(array_map(static fn (MetricComparison $metric): Status => $metric->status, $metrics)),
			true,
		);
	}

	private function compareMetric(Metric $metric, ?int $expected, int $measured, TolerancePolicy $tolerance): MetricComparison
	{
		if ($expected === null) {
			return new MetricComparison($metric, null, $measured, null, Status::New);
		}

		$allowed = $tolerance->allowedChange($metric, $expected);
		if ($expected === 0) {
			return new MetricComparison($metric, $expected, $measured, $allowed, $measured === 0 ? Status::Ok : Status::Slower);
		}

		$change = ($measured - $expected) / $expected;
		if ($change > $allowed) {
			$status = Status::Slower;
		} elseif ($change < -$allowed) {
			$status = Status::Faster;
		} else {
			$status = Status::Ok;
		}

		return new MetricComparison($metric, $expected, $measured, $allowed, $status);
	}

}
