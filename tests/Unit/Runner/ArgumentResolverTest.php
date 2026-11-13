<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Runner;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionMethod;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Meter;
use Tally\Runner\ArgumentResolver;
use Tally\Runner\MeterSession;
use Tally\ScratchDirectory;
use Tally\Tests\FakeMemory;
use Tally\Tests\RecordingCounter;
use function array_map;

final class ArgumentResolverTest extends TestCase
{

	/**
	 * @return iterable<string, array{ReflectionFunction, list<mixed>, list<mixed>}>
	 */
	public static function resolveProvider(): iterable
	{
		yield 'no parameters' => [new ReflectionFunction(static function (): void {
		}), [], []];
		yield 'the data set in order' => [new ReflectionFunction(static function (int $a, string $b): void {
		}), [1, 'b'], [1, 'b']];
		yield 'services first' => [new ReflectionFunction(static function (Meter $meter, ScratchDirectory $scratch, int $a): void {
		}), [1], ['meter', 'scratch', 1]];
		yield 'services between data' => [new ReflectionFunction(static function (int $a, ScratchDirectory $scratch, string $b, Meter $meter): void {
		}), [1, 'b'], [1, 'scratch', 'b', 'meter']];
		yield 'a nullable service' => [new ReflectionFunction(static function (?Meter $meter): void {
		}), [], ['meter']];
		yield 'the default of a parameter the data set leaves out' => [new ReflectionFunction(static function (int $a, string $b = 'default', ?Meter $meter = null): void {
		}), [1], [1, 'default', 'meter']];
		yield 'a data argument of the type of a service is still data when the parameter is untyped' => [new ReflectionFunction(static function ($a): void {
		}), ['scratch'], ['scratch']];
		yield 'a variadic parameter takes the rest' => [new ReflectionFunction(static function (Meter $meter, int $a, int ...$rest): void {
		}), [1, 2, 3], ['meter', 1, 2, 3]];
		yield 'a variadic parameter takes nothing' => [new ReflectionFunction(static function (int $a, int ...$rest): void {
		}), [1], [1]];
		yield 'a union with a service type is data' => [new ReflectionFunction(static function (Meter|int $a): void {
		}), [1], [1]];
	}

	/**
	 * @param list<mixed> $arguments
	 * @param list<mixed> $expected 'meter' and 'scratch' stand for the services
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('resolveProvider')]
	public function testResolve(ReflectionFunction $function, array $arguments, array $expected): void
	{
		$meter = new MeterSession(new RecordingCounter(), new FakeMemory());
		$scratch = new ScratchDirectory('/tmp/scratch');

		$resolved = (new ArgumentResolver())->resolve($function, $arguments, $meter, $scratch);

		$this->assertSame($expected, array_map(static fn (mixed $argument): mixed => match ($argument) {
			$meter => 'meter',
			$scratch => 'scratch',
			default => $argument,
		}, $resolved));
	}

	/**
	 * @return iterable<string, array{list<mixed>, string}>
	 */
	public static function mismatchProvider(): iterable
	{
		yield 'too few' => [[1], 'Tally\Tests\Unit\Runner\ArgumentResolverTest::method(): the data set has 1 argument, but parameter $b needs one too.'];
		yield 'none' => [[], 'Tally\Tests\Unit\Runner\ArgumentResolverTest::method(): the data set has 0 arguments, but parameter $a needs one too.'];
		yield 'too many' => [[1, 'b', 3], 'Tally\Tests\Unit\Runner\ArgumentResolverTest::method(): the data set has 3 arguments, but the method takes 2.'];
		yield 'far too many' => [[1, 'b', 3, 4], 'Tally\Tests\Unit\Runner\ArgumentResolverTest::method(): the data set has 4 arguments, but the method takes 2.'];
	}

	/**
	 * @param list<mixed> $arguments
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('mismatchProvider')]
	public function testMismatch(array $arguments, string $message): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage($message);

		(new ArgumentResolver())->resolve(
			new ReflectionMethod($this, 'method'),
			$arguments,
			new MeterSession(new RecordingCounter(), new FakeMemory()),
			new ScratchDirectory('/tmp/scratch'),
		);
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testAHookTakesTheLeadingArguments(): void
	{
		$scratch = new ScratchDirectory('/tmp/scratch');

		$resolved = (new ArgumentResolver())->resolveHook(
			new ReflectionFunction(static function (ScratchDirectory $scratch, int $a): void {
			}),
			[1, 'b', 3],
			new MeterSession(new RecordingCounter(), new FakeMemory()),
			$scratch,
		);

		$this->assertSame([$scratch, 1], $resolved);
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testAHookStillNeedsItsArguments(): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage('Tally\Tests\Unit\Runner\ArgumentResolverTest::method(): the data set has 1 argument, but parameter $b needs one too.');

		(new ArgumentResolver())->resolveHook(
			new ReflectionMethod($this, 'method'),
			[1],
			new MeterSession(new RecordingCounter(), new FakeMemory()),
			new ScratchDirectory('/tmp/scratch'),
		);
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testAClosureIsNamedAsSuch(): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage('{closure}(): the data set has 1 argument, but the method takes 0.');

		(new ArgumentResolver())->resolve(
			new ReflectionFunction(static function (): void {
			}),
			[1],
			new MeterSession(new RecordingCounter(), new FakeMemory()),
			new ScratchDirectory('/tmp/scratch'),
		);
	}

	public function method(int $a, Meter $meter, string $b): void
	{
	}

}
