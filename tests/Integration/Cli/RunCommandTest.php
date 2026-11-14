<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Cli;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Execution\DirectoryRemover;
use Tally\Measurement;
use Tally\Result\InvalidResultException;
use Tally\Result\MeasurementJson;
use Tally\Result\ResultsDirectory;
use Tally\Tests\InProcessTally;
use function array_map;
use function fclose;
use function file_get_contents;
use function fwrite;
use function getenv;
use function getmypid;
use function json_encode;
use function ksort;
use function proc_close;
use function proc_open;
use function realpath;
use function sprintf;
use function str_repeat;
use function sys_get_temp_dir;
use const JSON_THROW_ON_ERROR;

final class RunCommandTest extends TestCase
{

	private const RUN = __DIR__ . '/../../Fixtures/Run';
	private const BENCH = 'Tally\\Tests\\Fixtures\\Run\\RunBench';

	private string $output;

	#[Override]
	protected function setUp(): void
	{
		$this->output = sys_get_temp_dir() . '/tally-run-' . getmypid();
	}

	/**
	 * @throws InvalidResultException
	 */
	public function testCountsComparesAndKeepsTheResults(): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, $this->environment(), ['run', '--jobs=2', '--output=' . $this->output, self::RUN]);

		$this->assertSame(1, $exitCode, $errorOutput);
		$this->assertStringStartsWith("Tally counts 4 cases under CodSpeed's CPU simulation, 2 at once.\n\n", $output);
		$this->assertMatchesRegularExpression('~\n\n[.SEN]{4} +4 / 4 \(100%\)\n\nBenchmarks failed: ~', $output);
		$this->assertStringContainsString('Benchmarks failed: 1 slower, 1 not measured, 1 new, 1 unchanged', $output);
		$this->assertStringContainsString("\n  " . self::BENCH . "::benchThrows\n    RuntimeException: It broke.\n    at " . realpath(self::RUN . '/RunBench.php') . ":27\n", $output);
		$this->assertStringNotContainsString('RunBench::benchOk ', $output);
		$this->assertStringContainsString(self::BENCH . '::benchSlower   instructions     1.000K     2.000K   +100.0 %', $output);
		$this->assertStringContainsString("Reproduce locally\n  vendor/bin/tally run --filter='" . self::BENCH . "::benchSlower'", $output);
		$this->assertStringContainsString("Accept the new numbers\n  vendor/bin/tally accept '" . $this->output . "'", $output);
		$this->assertDirectoryDoesNotExist(sys_get_temp_dir() . '/tally-' . getmypid());

		$results = array_map(
			static fn (Measurement $measurement): array|string => $measurement->failure ?? $measurement->metrics->toArray(),
			(new ResultsDirectory(new MeasurementJson()))->read($this->output),
		);
		ksort($results);
		$this->assertSame([
			self::BENCH . '::benchNew' => ['instructions' => 1204, 'cycles' => 1404, 'memory' => 0],
			self::BENCH . '::benchOk' => ['instructions' => 1000, 'cycles' => 1500, 'memory' => 0],
			self::BENCH . '::benchSlower' => ['instructions' => 2000, 'cycles' => 3000, 'memory' => 0],
			self::BENCH . '::benchThrows' => "RuntimeException: It broke.\nat " . realpath(self::RUN . '/RunBench.php') . ':27',
		], $results);
	}

	/**
	 * @throws InvalidResultException
	 */
	public function testComparesTheGrowthWithTheBaseThatFinishesLater(): void
	{
		$growth = 'Tally\\Tests\\Fixtures\\Growth\\GrowthBench::benchLinear';
		$environment = $this->environment();
		$environment['TALLY_FAKE_COUNTS'] = json_encode([
			$growth . '@large' => [2500, 3000, 100, 150],
			$growth . '@small' => [1100, 1500, 100, 150],
		], JSON_THROW_ON_ERROR);
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, $environment, ['run', '--jobs=1', '--filter=@large', '--output=' . $this->output . '-growth', __DIR__ . '/../../Fixtures/Growth']);

		$this->assertSame(1, $exitCode, $errorOutput);
		$this->assertSame(
			"Tally counts 2 cases under CodSpeed's CPU simulation, 1 at once.\n\n"
			. 'S.' . str_repeat(' ', 65) . " 2 / 2 (100%)\n\n"
			. "Benchmarks failed: 1 slower, 1 unchanged\n"
			. "Allowed difference: instructions grow at most as O(n) from the smallest data set, ±10 % of each metric\n\n"
			. "Slower than expected\n"
			. "These benchmarks do more work than expected. Find out why before merging. If it is the price of a fix, loosen the complexity class.\n\n"
			. "  Benchmark                                                    Metric         Expected   Measured    Change   Allowed\n"
			. '  ' . $growth . "@large   instructions     2.000K     2.400K   +20.0 %     ±10 %\n\n"
			. "Reproduce locally\n"
			. "  vendor/bin/tally run --filter='" . $growth . "@large'\n",
			$output,
		);

		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, $environment, ['accept', $this->output . '-growth', __DIR__ . '/../../Fixtures/Growth']);
		(new DirectoryRemover())->remove($this->output . '-growth');
		$this->assertSame(0, $exitCode, $errorOutput);
		$this->assertSame("Accepted the numbers of 0 cases.\n", $output);
	}

	public function testPassesWhenEveryCaseMatches(): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, $this->environment(), ['run', '--filter=benchOk', self::RUN]);

		$this->assertSame(0, $exitCode, $errorOutput);
		$this->assertStringContainsString('Tally counts 1 case under', $output);
		$this->assertStringContainsString("Benchmarks passed: all 1 match their expectations\n", $output);
	}

	public function testAShardWithoutCases(): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, $this->environment(), ['run', '--shard=5/5', self::RUN]);

		$this->assertSame(0, $exitCode, $errorOutput);
		$this->assertSame("This shard has no cases to count.\n", $output);
	}

	public function testFailsBeforeCountingWhenAnExpectationCannotBeFound(): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, $this->environment(), ['run', __DIR__ . '/../../Fixtures/NonLiteral']);

		$this->assertSame(2, $exitCode);
		$this->assertSame('', $output);
		$this->assertSame("#[ExpectedFor] on line 22: write the data set as a string literal, so tally accept can find the attribute.\n", $errorOutput);
	}

	public function testAPartMissingFromTheProfileIsNotMeasured(): void
	{
		$environment = ['TALLY_FAKE_COUNTS' => '{}'] + $this->environment();

		[$exitCode, $output] = InProcessTally::run(__DIR__, $environment, ['run', '--filter=benchOk', self::RUN]);

		$this->assertSame(1, $exitCode);
		$this->assertStringContainsString("Callgrind's profile has no part named " . self::BENCH . '::benchOk.', $output);
	}

	public function testKillsACaseThatRunsTooLong(): void
	{
		[$exitCode, $output] = InProcessTally::run(__DIR__, $this->environment(), ['run', '--config=' . __DIR__ . '/../../Fixtures/Timeout/tally.php']);

		$this->assertSame(1, $exitCode);
		$this->assertStringContainsString("The native run did not finish in 1 second.\n", $output);
	}

	public function testWritesTheJobSummaryAndAnnotationsInGitHubActions(): void
	{
		$summary = $this->output . '-summary.md';
		$environment = [
			'GITHUB_ACTIONS' => 'true',
			'GITHUB_RUN_ID' => '42',
			'GITHUB_REPOSITORY' => 'phpstan/tally',
			'GITHUB_STEP_SUMMARY' => $summary,
		] + $this->environment();

		$repository = (string) realpath(__DIR__ . '/../../..');
		[$exitCode, $output] = InProcessTally::run($repository, $environment, ['run', '--filter=/benchSlower|benchNew/', self::RUN]);

		$this->assertSame(1, $exitCode);
		$this->assertStringContainsString('::error file=tests/Fixtures/Run/RunBench.php,line=16,title=', $output);
		$this->assertStringContainsString("gh run download 42 -R phpstan/tally -p 'tally-*' -D tmp/tally", $output);
		$patch = "--- a/tests/Fixtures/Run/RunBench.php\n+++ b/tests/Fixtures/Run/RunBench.php\n@@ -18,6 +18,7 @@\n \t{\n \t}\n \n"
			. "+\t#[Expected(instructions: '1.204K', cycles: '1.404K', memory: '0B')]\n \tpublic function benchNew(): void\n \t{\n \t}\n";
		$this->assertStringContainsString("Apply this patch with git apply, or accept the new numbers as above.\n\n" . $patch, $output);

		$markdown = (string) file_get_contents($summary);
		$this->assertStringStartsWith('## Benchmarks failed: 1 slower, 1 new', $markdown);
		$this->assertStringContainsString("```diff\n" . $patch . "```\n", $markdown);

		// the patch applies to the benchmark as it is
		$check = proc_open(['git', 'apply', '--check', '-'], [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $repository);
		self::assertNotFalse($check);
		self::assertArrayHasKey(0, $pipes);
		fwrite($pipes[0], $patch);
		fclose($pipes[0]);
		$this->assertSame(0, proc_close($check));
	}

	public function testAnnotationsPointToFilesInTheWorkspaceFromASubdirectory(): void
	{
		$environment = ['GITHUB_ACTIONS' => 'true', 'GITHUB_WORKSPACE' => (string) realpath(__DIR__ . '/../../..')] + $this->environment();

		[, $output] = InProcessTally::run(__DIR__, $environment, ['run', '--filter=benchSlower', self::RUN]);

		$this->assertStringContainsString('::error file=tests/Fixtures/Run/RunBench.php,line=16,title=', $output);
	}

	public function testReportsAJobSummaryThatCannotBeWritten(): void
	{
		$environment = ['GITHUB_ACTIONS' => 'true', 'GITHUB_STEP_SUMMARY' => '/nonexistent/summary.md'] + $this->environment();

		[, , $errorOutput] = InProcessTally::run(__DIR__, $environment, ['run', '--filter=benchOk', self::RUN]);

		$this->assertSame("Cannot write the job summary to /nonexistent/summary.md.\n", $errorOutput);
	}

	public function testRefusesToStartWithoutABuildOfValgrind(): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::runWithCodSpeed('/nonexistent/codspeed/linux-riscv64', __DIR__, $this->environment(), ['run', self::RUN]);

		$this->assertSame(2, $exitCode);
		$this->assertSame('', $output);
		$this->assertSame(
			"Tally counts with CodSpeed's Valgrind, but has no build of it for linux-riscv64 in /nonexistent/codspeed.\n"
			. "Releases of Tally include builds for linux-x86_64 and linux-aarch64. In a clone of Tally, build it with make codspeed.\n",
			$errorOutput,
		);
	}

	public function testReportsAResultsDirectoryThatCannotBeCreated(): void
	{
		[$exitCode, , $errorOutput] = InProcessTally::run(__DIR__, $this->environment(), ['run', '--filter=benchOk', '--output=/nonexistent/results', self::RUN]);

		$this->assertSame(2, $exitCode);
		$this->assertSame("Cannot create /nonexistent/results.\n", $errorOutput);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function dataInvalidJobs(): iterable
	{
		yield 'zero' => ['0'];
		yield 'negative' => ['-1'];
		yield 'not a number' => ['many'];
	}

	#[DataProvider('dataInvalidJobs')]
	public function testInvalidJobs(string $jobs): void
	{
		[$exitCode, , $errorOutput] = InProcessTally::run(__DIR__, $this->environment(), ['run', '--jobs=' . $jobs, self::RUN]);

		$this->assertSame(2, $exitCode);
		$this->assertStringStartsWith(sprintf("Option --jobs needs a positive number, %s given.\n", $jobs), $errorOutput);
	}

	/**
	 * @return array<string, string>
	 */
	private function environment(): array
	{
		return [
			'PATH' => (string) getenv('PATH'),
			// the recording build of tally_hooks, which the workers load like the tests
			'PHP_INI_SCAN_DIR' => (string) getenv('PHP_INI_SCAN_DIR'),
			'TALLY_FAKE_COUNTS' => json_encode([
				self::BENCH . '::benchOk' => [1100, 1650, 100, 150],
				self::BENCH . '::benchSlower' => [2100, 3150, 100, 150],
				self::BENCH . '::benchNew' => [1304, 1554, 100, 150],
			], JSON_THROW_ON_ERROR),
		];
	}

}
