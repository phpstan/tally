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
		$session = new MeterSession($counter, new FakeMemory(0));

		$session->start();
		$session->pause();
		$session->resume();
		$session->exclude(static fn (): null => null);
		$session->stop('App\FooBench::benchFoo');

		$this->assertSame(['start', 'pause', 'resume', 'pause', 'resume', 'stop App\FooBench::benchFoo'], $counter->calls);
	}

	public function testExcludeReturnsWhatTheCallbackReturns(): void
	{
		$session = new MeterSession(new RecordingCounter(), new FakeMemory(0));
		$session->start();

		$result = new MeterStateException('the result');

		$this->assertSame($result, $session->exclude(static fn (): MeterStateException => $result));
	}

	public function testExcludeResumesWhenTheCallbackThrows(): void
	{
		$counter = new RecordingCounter();
		$session = new MeterSession($counter, new FakeMemory(0));
		$session->start();

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
		$session = new MeterSession(new RecordingCounter(), $memory);

		$session->start();
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
				$session->start();
				$session->pause();
				$session->pause();
			},
			'Meter::pause() was called while the meter is paused.',
		];
		yield 'resume without pause' => [
			static function (MeterSession $session): void {
				$session->start();
				$session->resume();
			},
			'Meter::resume() was called while the meter is not paused.',
		];
		yield 'stop while paused' => [
			static function (MeterSession $session): void {
				$session->start();
				$session->pause();
				$session->stop('part');
			},
			'The benchmark returned while the meter is paused, call Meter::resume() first.',
		];
		yield 'stop before start' => [
			static fn (MeterSession $session) => $session->stop('part'),
			'The meter was stopped before it started.',
		];
		yield 'start twice' => [
			static function (MeterSession $session): void {
				$session->start();
				$session->start();
			},
			'The meter has already started.',
		];
		yield 'pause after stop' => [
			static function (MeterSession $session): void {
				$session->start();
				$session->stop('part');
				$session->pause();
			},
			'Meter::pause() was called while the benchmark is not counted.',
		];
		yield 'start after stop' => [
			static function (MeterSession $session): void {
				$session->start();
				$session->stop('part');
				$session->start();
			},
			'The meter has already started.',
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

		$misuse(new MeterSession(new RecordingCounter(), new FakeMemory(0)));
	}

}
