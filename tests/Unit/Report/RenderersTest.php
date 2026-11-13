<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Report;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Report\ConsoleReport;
use Tally\Report\GitHubAnnotations;
use Tally\Report\MarkdownReport;
use Tally\Report\Report;
use Tally\Report\Rows;
use Tally\Report\TextTable;
use function file_get_contents;

final class RenderersTest extends TestCase
{

	private const REPRODUCE = "vendor/bin/tally run --codspeed --filter='App\\ABench::benchSlower@big'";
	private const ACCEPT = "gh run download 1 -D tmp/tally\nvendor/bin/tally accept tmp/tally";

	/**
	 * @return iterable<string, array{string, Closure(Report): string}>
	 */
	public static function goldenProvider(): iterable
	{
		$console = new ConsoleReport(new TextTable(), new Rows());

		yield 'summary' => ['summary.txt', static fn (Report $report): string => $console->summary($report, self::REPRODUCE, self::ACCEPT, false)];
		yield 'summary with colors' => ['summary-colors.txt', static fn (Report $report): string => $console->summary($report, self::REPRODUCE, self::ACCEPT, true)];
		yield 'table' => ['table.txt', static fn (Report $report): string => $console->table($report, false)];
		yield 'table with colors' => ['table-colors.txt', static fn (Report $report): string => $console->table($report, true)];
		yield 'markdown' => ['summary.md', static fn (Report $report): string => (new MarkdownReport(new Rows()))->render($report, self::REPRODUCE, self::ACCEPT)];
		yield 'annotations' => ['annotations.txt', static fn (Report $report): string => (new GitHubAnnotations(new Rows()))->render($report, '/repo')];
	}

	/**
	 * @param Closure(Report): string $render
	 */
	#[DataProvider('goldenProvider')]
	public function testRendersEveryStatus(string $golden, Closure $render): void
	{
		$this->assertSame(file_get_contents(__DIR__ . '/golden/' . $golden), $render(Comparisons::mixed()));
	}

	public function testSummaryOfMatchingBenchmarksHasNoCommands(): void
	{
		$report = new Report([Comparisons::compare('A::bench', 1, ['instructions' => 10], ['instructions' => 10], null)]);

		$this->assertSame(
			"Benchmarks passed: all 1 match their expectations\nAllowed difference: ±10 % of each metric\n",
			(new ConsoleReport(new TextTable(), new Rows()))->summary($report, 'reproduce', 'accept', false),
		);
		$this->assertSame(
			"## Benchmarks passed: all 1 match their expectations\n\nAllowed difference: ±10 % of each metric\n\n"
			. "<details><summary>All benchmarks</summary>\n\n"
			. "| Benchmark | Metric | Expected | Measured | Change | Allowed | Status |\n|---|---|--:|--:|--:|--:|---|\n"
			. "| `A::bench` | instructions | 10 | 10 | +0.0 % | ±10 % | ok |\n\n</details>\n",
			(new MarkdownReport(new Rows()))->render($report, 'reproduce', 'accept'),
		);
		$this->assertSame('', (new GitHubAnnotations(new Rows()))->render($report, '/repo'));
	}

	public function testNothingToAcceptWhenEveryProblemIsAFailure(): void
	{
		$report = new Report([
			Comparisons::compare('A::broken', 1, null, null, "It broke.\nSee the log."),
			Comparisons::compare('A::timeout', 3, null, null, 'It timed out.'),
			Comparisons::compare('A::fine', 2, ['instructions' => 10], ['instructions' => 10], null),
		]);

		$this->assertSame(
			"Benchmarks failed: 2 not measured, 1 unchanged\n"
			. "Allowed difference: ±10 % of each metric\n\n"
			. "Not measured\nThese benchmarks failed, timed out, or did not run. Their errors are below.\n\n"
			. "  Benchmark    Error\n"
			. "  A::broken    It broke.\n"
			. "  A::timeout   It timed out.\n"
			. "\nReproduce locally\n  reproduce\n",
			(new ConsoleReport(new TextTable(), new Rows()))->summary($report, 'reproduce', 'accept', false),
		);
		$this->assertSame(
			"## Benchmarks failed: 2 not measured, 1 unchanged\n\nAllowed difference: ±10 % of each metric\n\n"
			. "> [!WARNING]\n> **Not measured**\n> These benchmarks failed, timed out, or did not run. Their errors are below.\n\n"
			. "| Benchmark | Error |\n|---|---|\n| `A::broken` | It broke. |\n| `A::timeout` | It timed out. |\n\n"
			. "### Reproduce locally\n\n```\nreproduce\n```\n\n"
			. "<details><summary>All benchmarks</summary>\n\n"
			. "| Benchmark | Metric | Expected | Measured | Change | Allowed | Status |\n|---|---|--:|--:|--:|--:|---|\n"
			. "| `A::broken` | - | - | - | - | - | not measured |\n"
			. "| `A::timeout` | - | - | - | - | - | not measured |\n"
			. "| `A::fine` | instructions | 10 | 10 | +0.0 % | ±10 % | ok |\n\n</details>\n",
			(new MarkdownReport(new Rows()))->render($report, 'reproduce', 'accept'),
		);
	}

	public function testMarkdownEscapesPipes(): void
	{
		$report = new Report([Comparisons::compare('A::bench@a|b', 1, null, null, 'x | y')]);

		$this->assertStringContainsString("| `A::bench@a\\|b` | x \\| y |\n", (new MarkdownReport(new Rows()))->render($report, 'r', 'a'));
	}

	public function testAnnotationsOfAFileOutsideTheWorkingDirectoryAndOfSomethingFromNothing(): void
	{
		$report = new Report([Comparisons::compare('A::bench', 7, ['memory' => 0], ['memory' => 64], null)]);

		$this->assertSame(
			"::error file=/repo/benchmarks/ABench.php,line=7,title=A%3A%3Abench is slower than expected::memory from nothing (64B, expected 0B, allowed ±10 %25)\n",
			(new GitHubAnnotations(new Rows()))->render($report, '/elsewhere'),
		);
		// a prefix of the directory name is not the directory
		$this->assertStringContainsString('file=/repo/benchmarks/ABench.php,', (new GitHubAnnotations(new Rows()))->render($report, '/repo/bench'));
	}

}
