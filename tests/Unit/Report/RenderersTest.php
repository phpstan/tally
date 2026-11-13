<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Report;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Report\ConsoleReport;
use Tally\Report\GitHubAnnotations;
use Tally\Report\MarkdownReport;
use Tally\Report\NewExpectations;
use Tally\Report\RelativePath;
use Tally\Report\Report;
use Tally\Report\Rows;
use Tally\Report\TextTable;
use function file_get_contents;
use function strlen;
use function substr;

final class RenderersTest extends TestCase
{

	private const REPRODUCE = "vendor/bin/tally run --filter='App\\ABench::benchSlower@big'";
	private const ACCEPT = "gh run download 1 -D tmp/tally\nvendor/bin/tally accept tmp/tally";
	private const ALL_NEW = "<details><summary>All benchmarks</summary>\n\n"
		. "| Benchmark | Metric | Expected | Measured | Change | Allowed | Status |\n|---|---|--:|--:|--:|--:|---|\n"
		. "| `A::bench` | instructions | - | 10 | - | - | new |\n\n</details>\n";

	/**
	 * @return iterable<string, array{string, Closure(Report): string}>
	 */
	public static function goldenProvider(): iterable
	{
		$console = new ConsoleReport(new TextTable(), new Rows());

		yield 'summary' => ['summary.txt', static fn (Report $report): string => $console->summary($report, self::REPRODUCE, self::ACCEPT, self::newExpectations(), false)];
		yield 'summary with colors' => ['summary-colors.txt', static fn (Report $report): string => $console->summary($report, self::REPRODUCE, self::ACCEPT, self::newExpectations(), true)];
		yield 'failures' => ['failures.txt', static fn (Report $report): string => $console->failures($report, false)];
		yield 'failures with colors' => ['failures-colors.txt', static fn (Report $report): string => $console->failures($report, true)];
		yield 'table' => ['table.txt', static fn (Report $report): string => $console->table($report, false)];
		yield 'table with colors' => ['table-colors.txt', static fn (Report $report): string => $console->table($report, true)];
		yield 'markdown' => ['summary.md', static fn (Report $report): string => (new MarkdownReport(new Rows()))->render($report, self::REPRODUCE, self::ACCEPT, self::newExpectations())];
		yield 'annotations' => ['annotations.txt', static fn (Report $report): string => (new GitHubAnnotations(new Rows(), new RelativePath()))->render($report, '/repo')];
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
			(new ConsoleReport(new TextTable(), new Rows()))->summary($report, 'reproduce', 'accept', NewExpectations::none(), false),
		);
		$this->assertSame(
			"## Benchmarks passed: all 1 match their expectations\n\nAllowed difference: ±10 % of each metric\n\n"
			. "<details><summary>All benchmarks</summary>\n\n"
			. "| Benchmark | Metric | Expected | Measured | Change | Allowed | Status |\n|---|---|--:|--:|--:|--:|---|\n"
			. "| `A::bench` | instructions | 10 | 10 | +0.0 % | ±10 % | ok |\n\n</details>\n",
			(new MarkdownReport(new Rows()))->render($report, 'reproduce', 'accept', NewExpectations::none()),
		);
		$this->assertSame('', (new GitHubAnnotations(new Rows(), new RelativePath()))->render($report, '/repo'));
		$this->assertSame('', (new ConsoleReport(new TextTable(), new Rows()))->failures($report, false));
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
			(new ConsoleReport(new TextTable(), new Rows()))->summary($report, 'reproduce', 'accept', NewExpectations::none(), false),
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
			(new MarkdownReport(new Rows()))->render($report, 'reproduce', 'accept', NewExpectations::none()),
		);
		$this->assertSame(
			"2 benchmarks were not measured:\n\n1) A::broken\nIt broke.\nSee the log.\n\n2) A::timeout\nIt timed out.\n",
			(new ConsoleReport(new TextTable(), new Rows()))->failures($report, false),
		);
	}

	/**
	 * @return iterable<string, array{NewExpectations, string}>
	 */
	public static function dataNewExpectations(): iterable
	{
		$title = "### Expected numbers of the new benchmarks\n\n";
		$text = "Apply this patch with git apply, or accept the new numbers as above.\n\n";

		yield 'none' => [NewExpectations::none(), ''];
		yield 'a patch' => [new NewExpectations("+a\n", []), $title . $text . "```diff\n+a\n```\n\n"];
		yield 'a patch with backticks' => [new NewExpectations("+`a` ```` ``\n", []), $title . $text . "`````diff\n+`a` ```` ``\n`````\n\n"];
		yield 'only failures' => [new NewExpectations('', ['A::bench' => 'It broke.']), $title . "- Cannot add the expected numbers of `A::bench`: It broke.\n\n"];
	}

	#[DataProvider('dataNewExpectations')]
	public function testMarkdownNewExpectations(NewExpectations $newExpectations, string $expected): void
	{
		$report = new Report([Comparisons::compare('A::bench', 1, null, ['instructions' => 10], null)]);
		$withoutPatch = (new MarkdownReport(new Rows()))->render($report, 'r', 'a', NewExpectations::none());

		$this->assertStringEndsWith(self::ALL_NEW, $withoutPatch);
		$this->assertSame(
			substr($withoutPatch, 0, -strlen(self::ALL_NEW)) . $expected . self::ALL_NEW,
			(new MarkdownReport(new Rows()))->render($report, 'r', 'a', $newExpectations),
		);
	}

	public function testConsoleNewExpectationsWithOnlyFailures(): void
	{
		$report = new Report([Comparisons::compare('A::bench', 1, null, ['instructions' => 10], null)]);

		$this->assertStringEndsWith(
			"\nExpected numbers of the new benchmarks\nCannot add the expected numbers of A::bench: It broke.\n",
			(new ConsoleReport(new TextTable(), new Rows()))->summary($report, 'r', 'a', new NewExpectations('', ['A::bench' => 'It broke.']), false),
		);
	}

	public function testMarkdownEscapesPipes(): void
	{
		$report = new Report([Comparisons::compare('A::bench@a|b', 1, null, null, 'x | y')]);

		$this->assertStringContainsString("| `A::bench@a\\|b` | x \\| y |\n", (new MarkdownReport(new Rows()))->render($report, 'r', 'a', NewExpectations::none()));
	}

	public function testAnnotationsOfAFileOutsideTheWorkingDirectoryAndOfSomethingFromNothing(): void
	{
		$report = new Report([Comparisons::compare('A::bench', 7, ['memory' => 0], ['memory' => 64], null)]);

		$this->assertSame(
			"::error file=/repo/benchmarks/ABench.php,line=7,title=A%3A%3Abench is slower than expected::memory from nothing (64B, expected 0B, allowed ±10 %25)\n",
			(new GitHubAnnotations(new Rows(), new RelativePath()))->render($report, '/elsewhere'),
		);
		// a prefix of the directory name is not the directory
		$this->assertStringContainsString('file=/repo/benchmarks/ABench.php,', (new GitHubAnnotations(new Rows(), new RelativePath()))->render($report, '/repo/bench'));
	}

	private static function newExpectations(): NewExpectations
	{
		return new NewExpectations(
			"--- a/benchmarks/ABench.php\n+++ b/benchmarks/ABench.php\n@@ -28,6 +28,7 @@\n \t}\n \n+\t#[Expected(instructions: '1.204K', cycles: '1.387K', memory: '0B')]\n \tpublic function benchNew(): void\n \t{\n \t}\n"
			. "--- a/benchmarks/data/bug-1234.php\n+++ b/benchmarks/data/bug-1234.php\n@@ -1,2 +1,2 @@\n-<?php // instructions: ?, cycles: ?, memory: ?\n+<?php // instructions: 1.2K, cycles: 1.4K, memory: 0B\n \n",
			['App\\ABench::benchNewer' => '#[ExpectedFor] on line 31: write the data set as a string literal, so tally accept can find the attribute.'],
		);
	}

}
