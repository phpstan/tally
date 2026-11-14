<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Execution;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\CaseId;
use Tally\Execution\CaseOutcome;
use Tally\Execution\WorkerRun;
use Tally\Measurement;
use Tally\Metric;
use Tally\Metrics;
use Tally\Process\ProcessCommand;
use Tally\Process\ProcessResult;
use function implode;
use function range;

final class CaseOutcomeTest extends TestCase
{

	private const ID = 'App\FooBench::benchFoo@a';

	/**
	 * @return iterable<string, array{non-empty-list<WorkerRun>, array<string, Metrics>, array<string, int>|string}>
	 */
	public static function dataMeasurement(): iterable
	{
		$baseline = Metrics::none()->with(Metric::Instructions, 100)->with(Metric::Cycles, 150);
		$counted = Metrics::none()->with(Metric::Instructions, 1100)->with(Metric::Cycles, 1650);
		$parts = [self::ID . '#baseline' => $baseline, self::ID => $counted, 'Other::benchOther#baseline' => Metrics::none()];
		$native = self::workerRun('The native run', ProcessResult::exited(self::command(60), 0), self::memory(1024), 'native output');
		$thrown = Measurement::failed(self::id(), 'RuntimeException: It broke.');

		yield 'the counted part minus the baseline, with the memory of the counted run' => [
			[$native, self::workerRun('The counted run', ProcessResult::exited(self::command(1800), 0), self::memory(2048), '')],
			$parts,
			['instructions' => 1000, 'cycles' => 1500, 'memory' => 2048],
		];
		yield 'no counted part' => [[$native], [self::ID . '#baseline' => $baseline], "Callgrind's profile has no part named App\\FooBench::benchFoo@a."];
		yield 'no baseline' => [[$native], [self::ID => $counted], "Callgrind's profile has no part named App\\FooBench::benchFoo@a#baseline."];
		yield 'no parts' => [[$native], [], "Callgrind's profile has no part named App\\FooBench::benchFoo@a."];
		yield 'the first failure' => [
			[
				self::workerRun('The native run', ProcessResult::exited(self::command(60), 1), $thrown, ''),
				self::workerRun('The counted run', ProcessResult::timedOut(self::command(60)), null, ''),
			],
			$parts,
			'RuntimeException: It broke.',
		];
		yield 'a failure after a success' => [
			[$native, self::workerRun('The counted run', ProcessResult::timedOut(self::command(1800)), null, '')],
			$parts,
			'The counted run did not finish in 30 minutes.',
		];
		yield 'one second' => [[self::workerRun('The native run', ProcessResult::timedOut(self::command(1)), null, '')], [], 'The native run did not finish in 1 second.'];
		yield 'seconds' => [[self::workerRun('The native run', ProcessResult::timedOut(self::command(90)), self::memory(1), '')], [], 'The native run did not finish in 90 seconds.'];
		yield 'an hour' => [[self::workerRun('The counted run', ProcessResult::timedOut(self::command(3600)), null, '')], [], 'The counted run did not finish in 60 minutes.'];
		yield 'one minute' => [[self::workerRun('The native run', ProcessResult::timedOut(self::command(60)), null, '')], [], 'The native run did not finish in 1 minute.'];
		yield 'the benchmark threw' => [[self::workerRun('The native run', ProcessResult::exited(self::command(60), 1), $thrown, 'output')], $parts, 'RuntimeException: It broke.'];
		yield 'the worker crashed' => [
			[self::workerRun('The native run', ProcessResult::exited(self::command(60), 255), null, "PHP Fatal error:  Allowed memory size exhausted\n\n")],
			$parts,
			"The native run exited with code 255.\nPHP Fatal error:  Allowed memory size exhausted",
		];
		yield 'exited without output' => [[self::workerRun('The native run', ProcessResult::exited(self::command(60), 139), null, " \n")], $parts, 'The native run exited with code 139.'];
		yield 'exited with a result' => [[self::workerRun('The counted run', ProcessResult::exited(self::command(60), 1), self::memory(1), '')], $parts, 'The counted run exited with code 1.'];
		yield 'wrote no result' => [[self::workerRun('The native run', ProcessResult::exited(self::command(60), 0), null, 'Bye')], $parts, "The native run wrote no result.\nBye"];
		yield 'long output' => [
			[self::workerRun('The native run', ProcessResult::exited(self::command(60), 2), null, implode("\n", range(1, 25)) . "\n")],
			$parts,
			"The native run exited with code 2.\n(5 lines before)\n" . implode("\n", range(6, 25)),
		];
		yield 'output as long as the limit' => [
			[self::workerRun('The native run', ProcessResult::exited(self::command(60), 2), null, implode("\n", range(1, CaseOutcome::OUTPUT_LINES_LIMIT)))],
			$parts,
			"The native run exited with code 2.\n" . implode("\n", range(1, CaseOutcome::OUTPUT_LINES_LIMIT)),
		];
	}

	/**
	 * @param non-empty-list<WorkerRun> $runs
	 * @param array<string, Metrics> $parts
	 * @param array<string, int>|string $expected the metrics, or the failure
	 */
	#[DataProvider('dataMeasurement')]
	public function testMeasurement(array $runs, array $parts, array|string $expected): void
	{
		$measurement = (new CaseOutcome())->measurement(self::id(), $runs, $parts);

		$this->assertSame(self::ID, $measurement->id->toString());
		$this->assertSame($expected, $measurement->failure ?? $measurement->metrics->toArray());
	}

	private static function workerRun(string $name, ProcessResult $result, ?Measurement $written, string $output): WorkerRun
	{
		return new WorkerRun($name, $result, $written, $output);
	}

	private static function memory(int $bytes): Measurement
	{
		return Measurement::measured(self::id(), Metrics::none()->with(Metric::Memory, $bytes));
	}

	private static function id(): CaseId
	{
		return CaseId::fromString(self::ID);
	}

	private static function command(int $timeLimitSeconds): ProcessCommand
	{
		return new ProcessCommand(['php'], '/work/native.log', $timeLimitSeconds);
	}

}
