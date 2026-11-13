<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Execution;

use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tally\Attribute\RelativeTolerance;
use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Execution\CaseJobs;
use Tally\Execution\CaseOutcome;
use Tally\Execution\DirectoryRemover;
use Tally\Execution\WorkerCommandLine;
use Tally\Execution\WorkerSettings;
use Tally\Measurement;
use Tally\Metric;
use Tally\Metrics;
use Tally\Process\ProcessCommand;
use Tally\Process\ProcessResult;
use Tally\Result\CallgrindProfile;
use Tally\Result\MeasurementJson;
use Tally\Tests\InMemoryExpectations;
use function array_map;
use function file_put_contents;
use function getmypid;
use function sys_get_temp_dir;

final class CaseJobsTest extends TestCase
{

	private const ID = 'App\FooBench::benchFoo@a';

	private string $directory;

	private string $caseDirectory;

	#[Override]
	protected function setUp(): void
	{
		$this->directory = sys_get_temp_dir() . '/tally-jobs-' . getmypid();
		$this->caseDirectory = $this->directory . '/' . CaseId::fromString(self::ID)->fileName();
	}

	#[Override]
	protected function tearDown(): void
	{
		(new DirectoryRemover())->remove($this->directory);
	}

	public function testTheJobRunsTheCaseNativelyAndThenUnderCodSpeed(): void
	{
		$job = self::jobs()->job($this->settings($this->directory), self::case());

		$this->assertSame(self::ID, $job->subject->id->toString());
		$this->assertDirectoryExists($this->caseDirectory . '/scratch');
		$this->assertDirectoryExists($this->caseDirectory . '/profile');
		$commandLine = new WorkerCommandLine('php', 'bin/tally');
		$settings = $this->settings($this->directory);
		$id = CaseId::fromString(self::ID);
		$scratch = $this->caseDirectory . '/scratch';
		$this->assertEquals(
			[
				new ProcessCommand($commandLine->native($settings, $id, $scratch, $this->caseDirectory . '/native.json'), $this->caseDirectory . '/native.log', 60),
				new ProcessCommand($commandLine->counted($settings, $id, $scratch, $this->caseDirectory . '/counted.json', $this->caseDirectory . '/profile'), $this->caseDirectory . '/counted.log', 1800),
			],
			$job->steps,
		);
	}

	public function testReportsADirectoryThatCannotBeCreated(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Cannot create /nonexistent/tally/' . CaseId::fromString(self::ID)->fileName() . '/scratch.');

		self::jobs()->job($this->settings('/nonexistent/tally'), self::case());
	}

	public function testMeasuresTheCaseFromWhatItsRunsLeft(): void
	{
		$job = self::jobs()->job($this->settings($this->directory), self::case());
		$json = new MeasurementJson();
		file_put_contents($this->caseDirectory . '/native.json', $json->encode(self::memory(1)));
		file_put_contents($this->caseDirectory . '/counted.json', $json->encode(self::memory(2048)));
		file_put_contents($this->caseDirectory . '/profile/1.out', "events: Ir\ndesc: Trigger: Client Request: " . self::ID . "#baseline\ntotals: 100\n");
		file_put_contents($this->caseDirectory . '/profile/2.out', "events: Ir\ndesc: Trigger: Client Request: " . self::ID . "\ntotals: 1100\n");
		file_put_contents($this->caseDirectory . '/profile/valgrind.2.log', 'not a profile');

		$measurement = self::jobs()->measurement(
			$this->settings($this->directory),
			CaseId::fromString(self::ID),
			array_map(static fn (ProcessCommand $step): ProcessResult => ProcessResult::exited($step, 0), $job->steps),
		);

		$this->assertSame(['instructions' => 1000, 'cycles' => 1000, 'memory' => 2048], $measurement->metrics->toArray());
	}

	public function testAFailedNativeRunWithItsOutput(): void
	{
		$job = self::jobs()->job($this->settings($this->directory), self::case());
		file_put_contents($this->caseDirectory . '/native.log', "PHP Fatal error:  Out of memory\n");

		$measurement = self::jobs()->measurement($this->settings($this->directory), CaseId::fromString(self::ID), [ProcessResult::exited($job->steps[0], 255)]);

		$this->assertSame("The native run exited with code 255.\nPHP Fatal error:  Out of memory", $measurement->failure);
	}

	public function testAnInvalidResult(): void
	{
		$job = self::jobs()->job($this->settings($this->directory), self::case());
		file_put_contents($this->caseDirectory . '/native.json', (new MeasurementJson())->encode(self::memory(1)));
		file_put_contents($this->caseDirectory . '/counted.json', '{');

		$measurement = self::jobs()->measurement(
			$this->settings($this->directory),
			CaseId::fromString(self::ID),
			array_map(static fn (ProcessCommand $step): ProcessResult => ProcessResult::exited($step, 0), $job->steps),
		);

		$this->assertSame('CodSpeed wrote an invalid result: it is not JSON.', $measurement->failure);
	}

	private static function jobs(): CaseJobs
	{
		return new CaseJobs(new WorkerCommandLine('php', 'bin/tally'), new CaseOutcome(), new CallgrindProfile(), new MeasurementJson());
	}

	private function settings(string $workDirectory): WorkerSettings
	{
		return new WorkerSettings([], [], '/opt/codspeed', '/opt/libinstrument_hooks.so', $workDirectory, 60, 1800);
	}

	private static function case(): BenchmarkCase
	{
		return new BenchmarkCase(CaseId::fromString(self::ID), [], 0, [], [], new InMemoryExpectations(null), new RelativeTolerance(0.1));
	}

	private static function memory(int $bytes): Measurement
	{
		return Measurement::measured(CaseId::fromString(self::ID), Metrics::none()->with(Metric::Memory, $bytes));
	}

}
