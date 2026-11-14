<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Runner;

use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tally\Attribute\RelativeTolerance;
use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Discovery\ArgumentResolver;
use Tally\Discovery\ConstructorArguments;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Runner\CaseRunner;
use Tally\Runner\IdleCounter;
use Tally\Runner\MeterStateException;
use Tally\ScratchDirectory;
use Tally\Tests\AllocatingCounter;
use Tally\Tests\FakeMemory;
use Tally\Tests\Fixtures\Runner\Journal;
use Tally\Tests\Fixtures\Runner\RunnerBench;
use Tally\Tests\InMemoryExpectations;
use Tally\Tests\JournalCounter;
use Throwable;
use function array_last;

final class CaseRunnerTest extends TestCase
{

	#[Override]
	protected function setUp(): void
	{
		Journal::$entries = [];
		Journal::$memory = null;
	}

	#[Override]
	protected function tearDown(): void
	{
		Journal::$entries = [];
		Journal::$memory = null;
	}

	public function testRunsTheHooksAndWarmUpUncountedAndCountsOneCallMinusTheBaseline(): void
	{
		$case = self::case('benchName', 'x', 2, ['boot', 'also'], ['tearDown']);

		self::runner()->run($case, new ScratchDirectory('/scratch/x'), new JournalCounter());

		$this->assertSame([
			'construct',
			'boot x in /scratch/x',
			'excluded in a hook',
			'also',
			'counter prepare',
			'bench x, booted x',
			'excluded',
			'bench x, booted x',
			'excluded',
			'counter start',
			'counter stop ' . RunnerBench::class . '::benchName@x#warmup',
			'counter start',
			'counter stop ' . RunnerBench::class . '::benchName@x#baseline',
			'counter start',
			'bench x, booted x',
			'counter pause',
			'excluded',
			'counter resume',
			'counter stop ' . RunnerBench::class . '::benchName@x',
			'counter finish',
			'tear down x',
		], Journal::$entries);
	}

	public function testWithoutWarmUpTheCountedCallIsTheFirst(): void
	{
		self::runner()->run(self::case('benchName', 'y', 0, [], []), new ScratchDirectory('/scratch/y'), new JournalCounter());

		$this->assertSame([
			'construct',
			'counter prepare',
			'counter start',
			'counter stop ' . RunnerBench::class . '::benchName@y#warmup',
			'counter start',
			'counter stop ' . RunnerBench::class . '::benchName@y#baseline',
			'counter start',
			'bench y, not booted',
			'counter pause',
			'excluded',
			'counter resume',
			'counter stop ' . RunnerBench::class . '::benchName@y',
			'counter finish',
		], Journal::$entries);
	}

	public function testMeasuresTheMemoryOfTheCountedCallOnly(): void
	{
		$memory = new FakeMemory(1_000_000);
		Journal::$memory = $memory;
		$case = new BenchmarkCase(new CaseId(RunnerBench::class, 'benchAllocate', null), '/benchmarks/FooBench.php', [], 3, [], [], new InMemoryExpectations(null, '/benchmarks/InMemoryBench.php'), new RelativeTolerance(0.1), null);

		$metrics = (new CaseRunner(new ConstructorArguments(), new ArgumentResolver(), $memory, new IdleCounter()))->run($case, new ScratchDirectory('/scratch'), new JournalCounter());

		$this->assertSame(['memory' => 1000], $metrics->toArray());
		$this->assertSame(1_004_000, $memory->usage());
	}

	/**
	 * @throws Throwable
	 */
	public function testSubtractsTheMemoryOfTheBaseline(): void
	{
		$memory = new FakeMemory(1_000_000);
		Journal::$memory = $memory;
		$case = new BenchmarkCase(new CaseId(RunnerBench::class, 'benchAllocate', null), '/benchmarks/FooBench.php', [], 0, [], [], new InMemoryExpectations(null, '/benchmarks/InMemoryBench.php'), new RelativeTolerance(0.1), null);

		$metrics = (new CaseRunner(new ConstructorArguments(), new ArgumentResolver(), $memory, new IdleCounter()))->run($case, new ScratchDirectory('/scratch'), new AllocatingCounter($memory, 56));

		$this->assertSame(['memory' => 1000], $metrics->toArray());
	}

	/**
	 * @throws Throwable
	 */
	public function testRunsTheAfterMethodsWhenTheBenchmarkThrows(): void
	{
		try {
			self::runner()->run(self::case('benchThrows', 'z', 0, [], ['tearDown']), new ScratchDirectory('/scratch/z'), new JournalCounter());
			self::fail('The exception of the benchmark should have been rethrown.');
		} catch (RuntimeException $e) {
			$this->assertSame('The benchmark is broken.', $e->getMessage());
		}

		$this->assertSame([
			'construct',
			'counter prepare',
			'counter start',
			'counter stop ' . RunnerBench::class . '::benchThrows@z#warmup',
			'counter start',
			'counter stop ' . RunnerBench::class . '::benchThrows@z#baseline',
			'counter start',
			'bench throws',
			'counter finish',
			'tear down z',
		], Journal::$entries);
	}

	/**
	 * @throws Throwable
	 */
	public function testTheAfterMethodsRunWhenTheBenchmarkThrowsWhilePaused(): void
	{
		try {
			self::runner()->run(self::case('benchThrowsPaused', 't', 0, [], ['tearDown']), new ScratchDirectory('/scratch/t'), new JournalCounter());
			self::fail('The exception of the benchmark should have been rethrown.');
		} catch (RuntimeException $e) {
			$this->assertSame('Thrown while paused.', $e->getMessage());
		}

		$this->assertSame('tear down t', array_last(Journal::$entries));
	}

	public function testABenchmarkThatReturnsPausedFails(): void
	{
		$this->expectException(MeterStateException::class);
		$this->expectExceptionMessage('The benchmark returned while the meter is paused, call Meter::resume() first.');

		self::runner()->run(self::case('benchLeavesPaused', 'p', 0, [], []), new ScratchDirectory('/scratch/p'), new JournalCounter());
	}

	public function testAWarmUpCallThatReturnsPausedFails(): void
	{
		$this->expectException(MeterStateException::class);
		$this->expectExceptionMessage('The benchmark returned while the meter is paused, call Meter::resume() first.');

		self::runner()->run(self::case('benchLeavesPaused', 'p', 1, [], []), new ScratchDirectory('/scratch/p'), new JournalCounter());
	}

	public function testABeforeMethodThatReturnsPausedFails(): void
	{
		$this->expectException(MeterStateException::class);
		$this->expectExceptionMessage('The benchmark returned while the meter is paused, call Meter::resume() first.');

		self::runner()->run(self::case('benchName', 'p', 0, ['leavePaused'], []), new ScratchDirectory('/scratch/p'), new JournalCounter());
	}

	public function testArgumentsThatDoNotFitFail(): void
	{
		$case = new BenchmarkCase(new CaseId(RunnerBench::class, 'benchName', 'too-many'), '/benchmarks/FooBench.php', ['x', 'y'], 0, [], [], new InMemoryExpectations(null, '/benchmarks/InMemoryBench.php'), new RelativeTolerance(0.1), null);

		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage(RunnerBench::class . '::benchName(): the data set has 2 arguments, but the method takes 1.');

		self::runner()->run($case, new ScratchDirectory('/scratch'), new JournalCounter());
	}

	/**
	 * @throws Throwable
	 */
	public function testAMissingClassFails(): void
	{
		$case = new BenchmarkCase(new CaseId('App\MissingBench', 'benchFoo', null), '/benchmarks/FooBench.php', [], 0, [], [], new InMemoryExpectations(null, '/benchmarks/InMemoryBench.php'), new RelativeTolerance(0.1), null);

		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage('App\MissingBench::benchFoo: class App\MissingBench does not exist.');

		self::runner()->run($case, new ScratchDirectory('/scratch'), new JournalCounter());
	}

	private static function runner(): CaseRunner
	{
		return new CaseRunner(new ConstructorArguments(), new ArgumentResolver(), new FakeMemory(0), new IdleCounter());
	}

	/**
	 * @param list<string> $before
	 * @param list<string> $after
	 */
	private static function case(string $method, string $name, int $warmup, array $before, array $after): BenchmarkCase
	{
		return new BenchmarkCase(new CaseId(RunnerBench::class, $method, $name), '/benchmarks/FooBench.php', [$name], $warmup, $before, $after, new InMemoryExpectations(null, '/benchmarks/InMemoryBench.php'), new RelativeTolerance(0.1), null);
	}

}
