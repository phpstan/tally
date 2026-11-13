<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Runner;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Runner\MeterSession;
use Tally\Runner\MeterStateException;
use Tally\Tests\FakeMemory;
use Tally\Tests\RecordingCounter;

final class MeterSessionTest extends TestCase
{

	private const MIB = 1024 * 1024;

	public function testCountsTheSpanAndNamesItsPart(): void
	{
		$counter = new RecordingCounter();
		$session = new MeterSession(new FakeMemory(0));

		$session->start($counter);
		$session->pause();
		$session->resume();
		$session->exclude(static fn (): null => null);
		$session->stop('App\FooBench::benchFoo');

		$this->assertSame(['start', 'pause', 'resume', 'pause', 'resume', 'stop App\FooBench::benchFoo'], $counter->calls);
	}

	public function testExcludeReturnsWhatTheCallbackReturns(): void
	{
		$session = new MeterSession(new FakeMemory(0));
		$session->start(new RecordingCounter());

		$result = new MeterStateException('the result');

		$this->assertSame($result, $session->exclude(static fn (): MeterStateException => $result));
	}

	public function testExcludeResumesWhenTheCallbackThrows(): void
	{
		$counter = new RecordingCounter();
		$session = new MeterSession(new FakeMemory(0));
		$session->start($counter);

		$this->expectException(MeterStateException::class);
		$this->expectExceptionMessage('thrown by the callback');
		try {
			$session->exclude(static function (): void {
				throw new MeterStateException('thrown by the callback');
			});
		} finally {
			$this->assertSame(['start', 'pause', 'resume'], $counter->calls);
		}
	}

	/**
	 * A benchmark object keeps its meter for the whole case: its hooks, warm-up calls, baseline, and the counted call are spans of one session.
	 */
	public function testEachSpanHasItsOwnCounterAndMemory(): void
	{
		$memory = new FakeMemory(0);
		$session = new MeterSession($memory);
		$hook = new RecordingCounter();
		$counted = new RecordingCounter();

		$session->start($hook);
		$memory->allocate(5 * self::MIB);
		$session->exclude(static fn (): null => null);
		$this->assertSame(['memory' => 5 * self::MIB], $session->stop('hook')->toArray());

		$session->start($counted);
		$memory->allocate(self::MIB);
		$this->assertSame(['memory' => self::MIB], $session->stop('counted')->toArray());

		$this->assertSame(['start', 'pause', 'resume', 'stop hook'], $hook->calls);
		$this->assertSame(['start', 'stop counted'], $counted->calls);
	}

	/**
	 * A call that throws leaves its span unfinished, and the after methods still run.
	 */
	public function testANewSpanReplacesAnUnfinishedOne(): void
	{
		$memory = new FakeMemory(0);
		$session = new MeterSession($memory);
		$thrown = new RecordingCounter();
		$after = new RecordingCounter();

		$session->start($thrown);
		$session->pause();
		$memory->allocate(5 * self::MIB);
		$session->start($after);
		$memory->allocate(self::MIB);
		$session->exclude(static fn (): null => null);

		$this->assertSame(['memory' => self::MIB], $session->stop('after')->toArray());
		$this->assertSame(['start', 'pause'], $thrown->calls);
		$this->assertSame(['start', 'pause', 'resume', 'stop after'], $after->calls);
	}

	/**
	 * @return iterable<string, array{Closure(MeterSession, FakeMemory): void, int}>
	 */
	public static function memoryProvider(): iterable
	{
		yield 'nothing allocated' => [
			static function (): void {
			},
			0,
		];
		yield 'what is still allocated at the end' => [
			static function (MeterSession $session, FakeMemory $memory): void {
				$memory->allocate(3 * self::MIB);
			},
			3 * self::MIB,
		];
		yield 'the peak, not what is left at the end' => [
			static function (MeterSession $session, FakeMemory $memory): void {
				$memory->allocate(3 * self::MIB);
				$memory->free(3 * self::MIB);
				$memory->allocate(self::MIB);
			},
			3 * self::MIB,
		];
		yield 'allocations while paused do not count' => [
			static function (MeterSession $session, FakeMemory $memory): void {
				$session->pause();
				$memory->allocate(8 * self::MIB);
				$session->resume();
				$memory->allocate(self::MIB);
			},
			self::MIB,
		];
		yield 'a peak before the pause is kept' => [
			static function (MeterSession $session, FakeMemory $memory): void {
				$memory->allocate(5 * self::MIB);
				$memory->free(5 * self::MIB);
				$session->pause();
				$memory->allocate(8 * self::MIB);
				$session->resume();
				$memory->allocate(self::MIB);
			},
			5 * self::MIB,
		];
		yield 'memory kept from before the pause adds to what comes after it' => [
			static function (MeterSession $session, FakeMemory $memory): void {
				$memory->allocate(2 * self::MIB);
				$session->pause();
				$memory->allocate(8 * self::MIB);
				$session->resume();
				$memory->allocate(self::MIB);
			},
			3 * self::MIB,
		];
		yield 'memory freed while paused does not lower what comes after it' => [
			static function (MeterSession $session, FakeMemory $memory): void {
				$memory->allocate(2 * self::MIB);
				$session->pause();
				$memory->free(2 * self::MIB);
				$session->resume();
				$memory->allocate(self::MIB);
			},
			3 * self::MIB,
		];
		yield 'every paused span is left out' => [
			static function (MeterSession $session, FakeMemory $memory): void {
				$session->exclude(static fn () => $memory->allocate(8 * self::MIB));
				$memory->allocate(self::MIB);
				$session->exclude(static fn () => $memory->allocate(8 * self::MIB));
				$memory->allocate(self::MIB);
			},
			2 * self::MIB,
		];
		yield 'never below zero when the benchmark frees memory allocated before it' => [
			static function (MeterSession $session, FakeMemory $memory): void {
				$memory->free(self::MIB);
				$session->pause();
				$session->resume();
			},
			0,
		];
	}

	/**
	 * @param Closure(MeterSession, FakeMemory): void $benchmark
	 */
	#[DataProvider('memoryProvider')]
	public function testMemoryIsThePeakAboveTheStart(Closure $benchmark, int $expected): void
	{
		// allocated before the span, by a before method for example
		$memory = new FakeMemory(40 * self::MIB);
		$memory->allocate(20 * self::MIB);
		$memory->free(20 * self::MIB);
		$session = new MeterSession($memory);

		$session->start(new RecordingCounter());
		$benchmark($session, $memory);

		$this->assertSame(['memory' => $expected], $session->stop('part')->toArray());
	}

	/**
	 * @return iterable<string, array{Closure(MeterSession): mixed, string}>
	 */
	public static function misuseProvider(): iterable
	{
		yield 'pause before start' => [
			static fn (MeterSession $session) => $session->pause(),
			'Meter::pause() was called while the benchmark is not counted.',
		];
		yield 'resume before start' => [
			static fn (MeterSession $session) => $session->resume(),
			'Meter::resume() was called while the benchmark is not counted.',
		];
		yield 'exclude before start' => [
			static fn (MeterSession $session) => $session->exclude(static fn (): null => null),
			'Meter::pause() was called while the benchmark is not counted.',
		];
		yield 'pause twice' => [
			static function (MeterSession $session): void {
				$session->start(new RecordingCounter());
				$session->pause();
				$session->pause();
			},
			'Meter::pause() was called while the meter is paused.',
		];
		yield 'resume without pause' => [
			static function (MeterSession $session): void {
				$session->start(new RecordingCounter());
				$session->resume();
			},
			'Meter::resume() was called while the meter is not paused.',
		];
		yield 'stop while paused' => [
			static function (MeterSession $session): void {
				$session->start(new RecordingCounter());
				$session->pause();
				$session->stop('part');
			},
			'The benchmark returned while the meter is paused, call Meter::resume() first.',
		];
		yield 'stop before start' => [
			static fn (MeterSession $session) => $session->stop('part'),
			'The meter was stopped before it started.',
		];
		yield 'pause after stop' => [
			static function (MeterSession $session): void {
				$session->start(new RecordingCounter());
				$session->stop('part');
				$session->pause();
			},
			'Meter::pause() was called while the benchmark is not counted.',
		];
	}

	/**
	 * @param Closure(MeterSession): mixed $misuse
	 */
	#[DataProvider('misuseProvider')]
	public function testMisuse(Closure $misuse, string $message): void
	{
		$this->expectException(MeterStateException::class);
		$this->expectExceptionMessage($message);

		$misuse(new MeterSession(new FakeMemory(0)));
	}

}
