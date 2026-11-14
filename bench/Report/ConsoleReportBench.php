<?php declare(strict_types = 1);

namespace Tally\Bench\Report;

use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;
use Tally\Attribute\RelativeTolerance;
use Tally\Attribute\Warmup;
use Tally\Bench\SuiteSource;
use Tally\CaseId;
use Tally\Comparison\Comparator;
use Tally\Expectation\SourceLocation;
use Tally\Measurement;
use Tally\Metric;
use Tally\Report\ConsoleReport;
use Tally\Report\MarkdownReport;
use Tally\Report\NewExpectations;
use Tally\Report\Report;
use Tally\Report\Rows;
use Tally\Report\TextTable;
use function sprintf;

/**
 * Every run ends with the report of all its cases: the summary in the console, and in GitHub
 * Actions the job summary too. A suite with a broad regression has a long table.
 */
#[Warmup(1)]
final class ConsoleReportBench
{

	/**
	 * @return iterable<string, array{Report}>
	 */
	public static function reports(): iterable
	{
		foreach ([[100, 10], [1000, 1000]] as [$cases, $slower]) {
			yield sprintf('cases-%d-slower-%d', $cases, $slower) => [self::report($cases, $slower)];
		}
	}

	#[DataProvider('reports')]
	#[ExpectedFor('cases-100-slower-10', instructions: '2.743M', cycles: '3.210M', memory: '43.93KiB')]
	#[ExpectedFor('cases-1000-slower-1000', instructions: '160.2M', cycles: '202.0M', memory: '3.994MiB')]
	public function benchSummary(Report $report): void
	{
		(new ConsoleReport(new TextTable(), new Rows()))->summary($report, 'vendor/bin/tally run', 'vendor/bin/tally accept', new NewExpectations('', []), true);
	}

	#[DataProvider('reports')]
	#[ExpectedFor('cases-100-slower-10', instructions: '13.74M', cycles: '17.88M', memory: '182.9KiB')]
	#[ExpectedFor('cases-1000-slower-1000', instructions: '283.5M', cycles: '389.3M', memory: '2.067MiB')]
	public function benchMarkdown(Report $report): void
	{
		(new MarkdownReport(new Rows()))->render($report, 'vendor/bin/tally run', 'vendor/bin/tally accept', new NewExpectations('', []));
	}

	/**
	 * @param int $slower how many of the cases, from the first, count 20 % more instructions than expected
	 */
	private static function report(int $cases, int $slower): Report
	{
		$source = new SuiteSource();
		$comparator = new Comparator();
		$tolerance = new RelativeTolerance(0.1);
		$comparisons = [];
		for ($i = 1; $i <= $cases; $i++) {
			$id = new CaseId($source->className(1, 1), sprintf('benchMethod%d', $i), null);
			$expected = $source->measured($i);
			$measured = $i <= $slower ? $expected->with(Metric::Instructions, 1_481_480 * $i) : $expected;
			$comparisons[] = $comparator->compare($id, new SourceLocation(SuiteSource::FILE, $i), $expected, $tolerance, Measurement::measured($id, $measured));
		}

		return new Report($comparisons);
	}

}
