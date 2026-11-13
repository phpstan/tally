<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Cli;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Measurement;
use Tally\Metric;
use Tally\Result\InvalidResultException;
use Tally\Result\MeasurementJson;
use Tally\Tests\InProcessTally;
use Tally\Tests\InstrumentHooksFixture;
use function array_keys;
use function array_values;
use function dirname;
use function file_get_contents;
use function getmypid;
use function is_dir;
use function mkdir;
use function proc_close;
use function proc_open;
use function sprintf;
use function sys_get_temp_dir;
use const PHP_BINARY;

final class WorkerCommandTest extends TestCase
{

	private const WORKER = __DIR__ . '/../../Fixtures/Worker';
	private const BENCH = 'Tally\\Tests\\Fixtures\\Worker\\WorkerBench';

	private string $directory;

	#[Override]
	protected function setUp(): void
	{
		$this->directory = sys_get_temp_dir() . '/tally-worker-' . getmypid();
		if (is_dir($this->directory)) {
			return;
		}

		mkdir($this->directory);
	}

	/**
	 * @throws InvalidResultException
	 */
	public function testRunsTheCaseUncountedWithoutTheLibrary(): void
	{
		[$exitCode, $output, $errorOutput] = $this->worker(self::BENCH . '::benchAllocate', [], $this->directory);

		$this->assertSame(0, $exitCode, $errorOutput);
		$this->assertSame('', $output);
		$result = $this->writtenResult();
		$this->assertSame(self::BENCH . '::benchAllocate', $result->id->toString());
		// this process collects coverage, which allocates too; testMeasuresTheMemoryOfTheBenchmarkAlone checks the value
		$this->assertSame(['memory'], array_keys($result->metrics->toArray()));
	}

	/**
	 * @throws InvalidResultException
	 */
	public function testCountsTheCaseAndItsBaselineWithTheLibrary(): void
	{
		$library = InstrumentHooksFixture::compile(true);
		$recording = InstrumentHooksFixture::load($library);
		$recording->clear_recorded_calls();

		[$exitCode, , $errorOutput] = $this->worker(self::BENCH . '::benchAllocate', ['--instrument-hooks=' . $library], $this->directory);

		$this->assertSame(0, $exitCode, $errorOutput);
		$this->assertSame(
			sprintf("init\nstart\nstop\nexecuted %1\$d %2\$s::benchAllocate#warmup\nstart\nstop\nexecuted %1\$d %2\$s::benchAllocate#baseline\nstart\nstop\nexecuted %1\$d %2\$s::benchAllocate\n", (int) getmypid(), self::BENCH),
			$recording->recorded_calls(),
		);
		$this->assertSame(['memory'], array_keys($this->writtenResult()->metrics->toArray()));
	}

	/**
	 * @return iterable<string, array{string, int, int}>
	 */
	public static function dataMemory(): iterable
	{
		yield 'an empty benchmark costs nothing beyond the baseline' => ['benchEmpty', 0, 0];
		// PHP's allocator rounds a large string up to whole pages
		yield 'a string of 100 000 bytes' => ['benchAllocate', 100_000, 100_000 + 4096];
		// the baseline takes the argument the way the benchmark does, without allocating an array for it
		yield 'a copy of an argument' => ['benchCopy@ten', 376, 376];
	}

	/**
	 * Runs bin/tally in its own process, where coverage collection does not allocate.
	 *
	 * @throws InvalidResultException
	 */
	#[DataProvider('dataMemory')]
	public function testMeasuresTheMemoryOfTheBenchmarkAlone(string $method, int $minimum, int $maximum): void
	{
		$command = [
			PHP_BINARY,
			InProcessTally::BINARY,
			'worker',
			'--case=' . self::BENCH . '::' . $method,
			'--scratch=' . $this->directory,
			'--result=' . $this->directory . '/result.json',
			self::WORKER,
		];
		$process = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
		self::assertNotFalse($process);
		$this->assertSame(0, proc_close($process));

		$memory = $this->writtenResult()->metrics->get(Metric::Memory);
		$this->assertGreaterThanOrEqual($minimum, $memory);
		$this->assertLessThanOrEqual($maximum, $memory);
	}

	public function testCreatesTheScratchDirectory(): void
	{
		$scratch = $this->directory . '/new/scratch';

		[$exitCode] = $this->worker(self::BENCH . '::benchScratch', [], $scratch);

		$this->assertSame(0, $exitCode);
		$this->assertStringEqualsFile($scratch . '/written', 'by the benchmark');
	}

	/**
	 * @throws InvalidResultException
	 */
	public function testWritesWhatTheBenchmarkThrew(): void
	{
		[$exitCode, $output] = $this->worker(self::BENCH . '::benchThrows', [], $this->directory);

		$this->assertSame(1, $exitCode);
		$this->assertSame('', $output);
		$result = $this->writtenResult();
		$this->assertSame(self::BENCH . '::benchThrows', $result->id->toString());
		$this->assertSame("RuntimeException: It broke.\nat tests/Fixtures/Worker/WorkerBench.php:48", $result->failure);
	}

	/**
	 * @throws InvalidResultException
	 */
	public function testRefusesToCountOutsideTheSimulation(): void
	{
		[$exitCode] = $this->worker(self::BENCH . '::benchAllocate', ['--instrument-hooks=' . InstrumentHooksFixture::compile(false)], $this->directory);

		$this->assertSame(1, $exitCode);
		$this->assertStringStartsWith("The benchmark does not run under CodSpeed's CPU simulation.\n", (string) $this->writtenResult()->failure);
	}

	public function testReportsAResultThatCannotBeWritten(): void
	{
		[$exitCode, , $errorOutput] = InProcessTally::run(__DIR__, [], ['worker', '--case=' . self::BENCH . '::benchAllocate', '--scratch=' . $this->directory, '--result=/nonexistent/result.json', self::WORKER]);

		$this->assertSame(2, $exitCode);
		$this->assertSame("Cannot write /nonexistent/result.json.\n", $errorOutput);
	}

	public function testUnknownCase(): void
	{
		[$exitCode, , $errorOutput] = $this->worker(self::BENCH . '::benchMissing', [], $this->directory);

		$this->assertSame(2, $exitCode);
		$this->assertStringStartsWith('There is no case ' . self::BENCH . "::benchMissing.\nRun tally worker --help for its options.\n", $errorOutput);
	}

	public function testHelp(): void
	{
		[$exitCode, $output] = InProcessTally::run(__DIR__, [], ['worker', '--help']);

		$this->assertSame(0, $exitCode);
		$this->assertSame(
			"Runs one case in this process, tally run starts it for every case\n\n"
			. "Usage: tally worker [options] [<path>...]\n\n"
			. "Options:\n"
			. "  --config=<file>               Configuration file, tally.php in the working directory by default\n"
			. "  --case=<id>                   The ID of the case to run\n"
			. "  --scratch=<directory>         The scratch directory of the case, created when it does not exist\n"
			. "  --result=<file>               Where to write the measurement as JSON\n"
			. "  --instrument-hooks=<library>  CodSpeed's instrument-hooks library, which counts the case under CodSpeed's CPU simulation\n"
			. "  --help                        Show this help\n",
			$output,
		);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function dataRequiredOption(): iterable
	{
		yield 'case' => ['case'];
		yield 'scratch' => ['scratch'];
		yield 'result' => ['result'];
	}

	#[DataProvider('dataRequiredOption')]
	public function testRequiredOption(string $option): void
	{
		$arguments = ['case' => '--case=' . self::BENCH . '::benchAllocate', 'scratch' => '--scratch=' . $this->directory, 'result' => '--result=' . $this->directory . '/result.json'];
		unset($arguments[$option]);

		[$exitCode, , $errorOutput] = InProcessTally::run(__DIR__, [], ['worker', ...array_values($arguments), self::WORKER]);

		$this->assertSame(2, $exitCode);
		$this->assertStringStartsWith(sprintf("Option --%s is required.\nRun tally worker --help for its options.\n", $option), $errorOutput);
	}

	/**
	 * @param list<string> $options
	 * @return array{int, string, string}
	 */
	private function worker(string $id, array $options, string $scratch): array
	{
		return InProcessTally::run(dirname(__DIR__, 3), [], [
			'worker',
			'--case=' . $id,
			'--scratch=' . $scratch,
			'--result=' . $this->directory . '/result.json',
			...$options,
			self::WORKER,
		]);
	}

	/**
	 * @throws InvalidResultException
	 */
	private function writtenResult(): Measurement
	{
		return (new MeasurementJson())->decode((string) file_get_contents($this->directory . '/result.json'));
	}

}
