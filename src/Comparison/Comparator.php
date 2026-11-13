<?php declare(strict_types = 1);

namespace Tally\Comparison;

use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Expectation\ExpectationException;
use Tally\Expectation\SourceLocation;
use Tally\Measurement;
use Tally\Metric;
use Tally\Metrics;
use function array_map;

/**
 * Compares the measured metrics of a case with the expected ones. A metric the run did not
 * count, like instructions in a run without CodSpeed, is left out. Nothing expected of a
 * metric that is measured above zero is slower, because no share of zero allows anything.
 */
final class Comparator
{

	public const NOT_RUN = 'It did not run.';

	/**
	 * @param Measurement|null $measurement null when the case did not run
	 * @throws ExpectationException
	 */
	public function compareCase(BenchmarkCase $case, ?Measurement $measurement): CaseComparison
	{
		return $this->compare($case->id, $case->expectations->location(), $case->expectations->read(), $case->tolerance, $measurement);
	}

	public function compare(CaseId $id, SourceLocation $location, ?Metrics $expected, TolerancePolicy $tolerance, ?Measurement $measurement): CaseComparison
	{
		if ($measurement === null || $measurement->failure !== null) {
			$metrics = [];
			foreach ($expected?->toArray() ?? [] as $metric => $value) {
				$metrics[] = new MetricComparison(Metric::from($metric), $value, null, null, Status::NotMeasured);
			}

			return new CaseComparison($id, $location, $tolerance, $metrics, $measurement->failure ?? self::NOT_RUN, Status::NotMeasured);
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
