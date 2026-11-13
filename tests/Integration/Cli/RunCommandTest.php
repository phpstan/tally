<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Cli;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Measurement;
use Tally\Result\InvalidResultException;
use Tally\Result\MeasurementJson;
use Tally\Result\ResultsDirectory;
use Tally\Tests\InProcessTally;
use Tally\Tests\InstrumentHooksFixture;
use function array_map;
use function file_get_contents;
use function getenv;
use function getmypid;
use function json_encode;
use function ksort;
use function realpath;
use function sprintf;
use function sys_get_temp_dir;
use const JSON_THROW_ON_ERROR;

final class RunCommandTest extends TestCase
{

	private const RUN = __DIR__ . '/../../Fixtures/Run';
	private const BENCH = 'Tally\\Tests\\Fixtures\\Run\\RunBench';
	private const FAKE_CODSPEED = __DIR__ . '/../../Fixtures/CodSpeed/bin';

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
		$this->assertMatchesRegularExpression('~\n\n[.SEN]{4} +4 / 4 \(100%\)\n\n1 benchmark was not measured:~', $output);
		$this->assertStringContainsString("1 benchmark was not measured:\n\n1) " . self::BENCH . "::benchThrows\nRuntimeException: It broke.\nat ", $output);
		$this->assertStringContainsString('Benchmarks failed: 1 slower, 1 not measured, 1 new, 1 unchanged', $output);
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

	public function testPassesWhenEveryCaseMatches(): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, $this->environment(), ['run', '--filter=benchOk', self::RUN]);

		$this->assertSame(0, $exitCode, $errorOutput);
		$this->assertStringContainsString('Tally counts 1 case under', $output);
		$this->assertStringContainsString("Benchmarks passed: all 1 match their expectations\n", $output);
	}

	public function testNoCases(): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, $this->environment(), ['run', '--filter=nothing', '--jobs=3', self::RUN]);

		$this->assertSame(0, $exitCode, $errorOutput);
		$this->assertSame("Tally counts 0 cases under CodSpeed's CPU simulation, 3 at once.\n\nNo benchmarks to compare\n", $output);
	}

	public function testAPartMissingFromTheProfileIsNotMeasured(): void
	{
		$environment = ['TALLY_FAKE_COUNTS' => '{}'] + $this->environment();

		[$exitCode, $output] = InProcessTally::run(__DIR__, $environment, ['run', '--filter=benchOk', self::RUN]);

		$this->assertSame(1, $exitCode);
		$this->assertStringContainsString("CodSpeed's profile has no part named " . self::BENCH . '::benchOk.', $output);
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

		[$exitCode, $output] = InProcessTally::run((string) realpath(__DIR__ . '/../../..'), $environment, ['run', '--filter=benchSlower', self::RUN]);

		$this->assertSame(1, $exitCode);
		$this->assertStringContainsString('::error file=tests/Fixtures/Run/RunBench.php,line=16,title=', $output);
		$this->assertStringContainsString("gh run download 42 -R phpstan/tally -p 'tally-*' -D tmp/tally", $output);
		$this->assertStringStartsWith('## Benchmarks failed: 1 slower', (string) file_get_contents($summary));
	}

	public function testReportsAJobSummaryThatCannotBeWritten(): void
	{
		$environment = ['GITHUB_ACTIONS' => 'true', 'GITHUB_STEP_SUMMARY' => '/nonexistent/summary.md'] + $this->environment();

		[, , $errorOutput] = InProcessTally::run(__DIR__, $environment, ['run', '--filter=benchOk', self::RUN]);

		$this->assertSame("Cannot write the job summary to /nonexistent/summary.md.\n", $errorOutput);
	}

	public function testRefusesToStartWithoutCodSpeed(): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, ['PATH' => '/nonexistent'], ['run', self::RUN]);

		$this->assertSame(2, $exitCode);
		$this->assertSame('', $output);
		$this->assertStringStartsWith("Tally counts with CodSpeed's CPU simulation, but the codspeed CLI is not on PATH.", $errorOutput);
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
			'PATH' => self::FAKE_CODSPEED . ':' . (string) getenv('PATH'),
			'INSTRUMENT_HOOKS_LIBRARY' => InstrumentHooksFixture::compile(true),
			'TALLY_FAKE_COUNTS' => json_encode([
				self::BENCH . '::benchOk' => [1100, 1650, 100, 150],
				self::BENCH . '::benchSlower' => [2100, 3150, 100, 150],
				self::BENCH . '::benchNew' => [1304, 1554, 100, 150],
			], JSON_THROW_ON_ERROR),
		];
	}

}
