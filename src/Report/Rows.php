<?php declare(strict_types = 1);

namespace Tally\Report;

use Tally\Comparison\CaseComparison;
use Tally\Comparison\MetricComparison;
use Tally\Comparison\Percent;
use Tally\Metric;

/** The cells of the report's tables, shared by the terminal and the markdown report */
final class Rows
{

	public const HEADER = ['Benchmark', 'Metric', 'Expected', 'Measured', 'Change', 'Allowed'];

	/** The columns with numbers */
	public const NUMBER_COLUMNS = [2, 3, 4, 5];

	/**
	 * One row per metric, the case's ID only on the first. A case without metrics gets one row.
	 *
	 * @return non-empty-list<list<string>>
	 */
	public function metrics(CaseComparison $case): array
	{
		$rows = [];
		foreach ($case->metrics as $metric) {
			$rows[] = [$rows === [] ? $case->id->toString() : '', ...$this->metric($metric)];
		}

		return $rows === [] ? [[$case->id->toString(), '-', '-', '-', '-', '-']] : $rows;
	}

	/**
	 * @return list<string> without the case
	 */
	private function metric(MetricComparison $metric): array
	{
		$change = $metric->change();

		return [
			$metric->metric->value,
			$this->value($metric->metric, $metric->expected),
			$this->value($metric->metric, $metric->measured),
			$change === null ? '-' : (new Percent($change))->signed(),
			$metric->allowedChange === null ? '-' : '±' . (new Percent($metric->allowedChange))->toString(),
		];
	}

	/** A value with its unit, - for none */
	public function value(Metric $metric, ?int $value): string
	{
		return $value === null ? '-' : $metric->format()->format($value);
	}

}
