<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Cli;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Tests\InProcessTally;
use function chmod;
use function file_get_contents;
use function file_put_contents;
use function getmypid;
use function is_dir;
use function mkdir;
use function sprintf;
use function str_replace;
use function sys_get_temp_dir;

final class CompareAndAcceptCommandTest extends TestCase
{

	private const COMPARE = __DIR__ . '/../../Fixtures/Compare';
	private const RESULTS = __DIR__ . '/../../Fixtures/Results/compare';
	private const BENCH = 'Tally\\Tests\\Fixtures\\Compare\\CompareBench';
	private const ACCEPT_BENCH = 'Tally\\Tests\\Fixtures\\Compare\\AcceptBench';
	private const RESULTS_ACCEPT = __DIR__ . '/../../Fixtures/Results/accept';
	private const READ_ONLY_BENCH = 'Tally\\Tests\\Fixtures\\Compare\\ReadOnlyBench';
	private const RESULTS_READ_ONLY = __DIR__ . '/../../Fixtures/Results/read-only';

	private string $directory;

	#[Override]
	protected function setUp(): void
	{
		$this->directory = sys_get_temp_dir() . '/tally-accept-' . getmypid();
		if (is_dir($this->directory)) {
			return;
		}

		mkdir($this->directory);
	}

	public function testComparesTheResultsOfAllShards(): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, [], ['compare', self::RESULTS, self::COMPARE]);

		$this->assertSame(1, $exitCode, $errorOutput);
		$this->assertStringStartsWith(
			"1 benchmark was not measured:\n\n1) " . self::BENCH . "::benchMissing\nIt did not run.\n\n"
			. "Benchmarks failed: 1 slower, 1 not measured, 1 unchanged\n",
			$output,
		);
		$this->assertStringContainsString("Accept the new numbers\n  vendor/bin/tally accept '" . self::RESULTS . "'\n", $output);
	}

	public function testReadsTheBenchmarksFromTheConfiguration(): void
	{
		[$exitCode, $output] = InProcessTally::run(__DIR__, [], ['compare', '--config=' . self::COMPARE . '/tally.php', self::RESULTS]);

		$this->assertSame(1, $exitCode);
		$this->assertStringContainsString('Benchmarks failed: 1 slower, 1 not measured, 1 unchanged', $output);
	}

	public function testTable(): void
	{
		[, $output] = InProcessTally::run(__DIR__, [], ['compare', '--table', '--filter=benchOk', self::RESULTS, self::COMPARE]);

		$this->assertStringStartsWith(
			"Benchmark                                            Metric         Expected   Measured   Change   Allowed   Status\n"
			. self::BENCH . "::benchOk   instructions     1.000K     1.000K   +0.0 %     ±10 %   ok\n",
			$output,
		);
		$this->assertStringContainsString("\n\nBenchmarks passed: all 1 match their expectations\n", $output);
	}

	public function testAcceptWritesTheChangedCasesOnly(): void
	{
		$bench = $this->copyBench('AcceptBench');
		$original = (string) file_get_contents($bench);

		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, [], ['accept', self::RESULTS_ACCEPT, $bench]);

		$this->assertSame(0, $exitCode, $errorOutput);
		$this->assertSame('slower  ' . self::ACCEPT_BENCH . "::benchSlower\n\nAccepted the numbers of 1 case.\n", $output);
		$this->assertSame(
			str_replace(
				"#[Expected(instructions: '1K', cycles: '1.5K', memory: '0B')]\n\tpublic function benchSlower()",
				"#[Expected(instructions: '2.000K', cycles: '3.000K', memory: '0B')]\n\tpublic function benchSlower()",
				$original,
			),
			file_get_contents($bench),
		);
	}

	public function testAcceptReportsAFileThatCannotBeWritten(): void
	{
		$bench = $this->copyBench('ReadOnlyBench');
		$writable = $this->copyBench('WritableBench');
		chmod($bench, 0444);

		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, [], ['accept', self::RESULTS_READ_ONLY, $bench, $writable]);
		chmod($bench, 0644);

		$this->assertSame(2, $exitCode);
		$this->assertSame(sprintf("Cannot accept %s::benchSlower: Cannot write %s.\n", self::READ_ONLY_BENCH, $bench), $errorOutput);
		// a case that cannot be accepted does not stop the others
		$this->assertSame('slower  Tally\\Tests\\Fixtures\\Compare\\WritableBench::benchSlower' . "\n\nAccepted the numbers of 1 case.\n", $output);
	}

	/**
	 * @return iterable<string, array{list<string>, string}>
	 */
	public static function dataUsageError(): iterable
	{
		foreach (['compare', 'accept'] as $command) {
			yield $command . ' without results' => [[$command], 'Pass the results directory that tally run --output wrote.'];
			yield $command . ' with missing results' => [[$command, '/nonexistent'], 'Results directory /nonexistent does not exist.'];
			yield $command . ' with invalid results' => [
				[$command, __DIR__ . '/../../Fixtures/Results/invalid'],
				__DIR__ . '/../../Fixtures/Results/invalid/broken.json is not a Tally result: it has neither metrics nor a failure.',
			];
		}
	}

	/**
	 * @param list<string> $arguments
	 */
	#[DataProvider('dataUsageError')]
	public function testUsageError(array $arguments, string $message): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, [], $arguments);

		$this->assertSame(2, $exitCode);
		$this->assertSame('', $output);
		$this->assertStringStartsWith($message . "\n\n", $errorOutput);
	}

	/**
	 * A copy of the fixture to rewrite, with its own class: PHP cannot declare a class twice.
	 */
	private function copyBench(string $class): string
	{
		$bench = $this->directory . '/' . $class . '.php';
		file_put_contents($bench, str_replace('class CompareBench', 'class ' . $class, (string) file_get_contents(self::COMPARE . '/CompareBench.php')));

		return $bench;
	}

}
