<?php declare(strict_types = 1);

namespace Tally\Report;

use Tally\Comparison\CaseComparison;
use Tally\Comparison\MetricComparison;
use Tally\Comparison\Percent;
use Tally\Comparison\Status;
use function implode;
use function sprintf;
use function str_replace;

/**
 * GitHub Actions workflow commands that annotate where each case's expectations are, one per
 * case that needs attention: an error for a failing case, a warning for one to accept.
 */
final class GitHubAnnotations
{

	public function __construct(private Rows $rows, private RelativePath $relativePath)
	{
	}

	/**
	 * @param string $workingDirectory annotations need paths relative to the repository
	 */
	public function render(Report $report, string $workingDirectory): string
	{
		$output = '';
		foreach ($report->sections() as $section) {
			foreach ($section->cases as $case) {
				$output .= sprintf(
					"::%s file=%s,line=%d,title=%s::%s\n",
					$section->status->fails() ? 'error' : 'warning',
					$this->escapeProperty($this->relativePath->of($case->location->file, $workingDirectory)),
					$case->location->line,
					$this->escapeProperty(sprintf($this->title($section->status), $case->id->toString())),
					$this->escapeData($this->message($case)),
				);
			}
		}

		return $output;
	}

	private function title(Status $status): string
	{
		return match ($status) {
			Status::Slower => '%s is slower than expected',
			Status::NotMeasured => '%s was not measured',
			Status::New => '%s has no expected numbers',
			Status::Faster, Status::Ok => '%s is faster than expected',
		};
	}

	private function message(CaseComparison $case): string
	{
		if ($case->failure !== null) {
			return $case->failure;
		}

		$lines = [];
		foreach ($case->metrics as $metric) {
			$lines[] = $this->metric($metric);
		}

		return implode("\n", $lines);
	}

	private function metric(MetricComparison $metric): string
	{
		$measured = $this->rows->value($metric->metric, $metric->measured);
		// nothing to compare with, a new case
		if ($metric->allowedChange === null) {
			return sprintf('%s %s', $metric->metric->value, $measured);
		}

		$change = $metric->change();

		return sprintf(
			'%s %s (%s, expected %s, allowed ±%s)',
			$metric->metric->value,
			$change === null ? 'from nothing' : (new Percent($change))->signed(),
			$measured,
			$this->rows->value($metric->metric, $metric->expected),
			(new Percent($metric->allowedChange))->toString(),
		);
	}

	/** https://github.com/actions/toolkit/blob/main/packages/core/src/command.ts */
	private function escapeData(string $text): string
	{
		return str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $text);
	}

	private function escapeProperty(string $text): string
	{
		return str_replace([':', ','], ['%3A', '%2C'], $this->escapeData($text));
	}

}
