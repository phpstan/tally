<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Discovery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionMethod;
use Tally\Discovery\ArgumentResolver;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Meter;
use Tally\ScratchDirectory;

final class ArgumentResolverTest extends TestCase
{

	/**
	 * @return iterable<string, array{ReflectionFunction, list<mixed>}>
	 */
	public static function dataResolve(): iterable
	{
		yield 'no parameters' => [new ReflectionFunction(static function (): void {
		}), []];
		yield 'the data set in order' => [new ReflectionFunction(static function (int $a, string $b): void {
		}), [1, 'b']];
		yield 'a data argument of the type of a service is data when the parameter is untyped' => [new ReflectionFunction(static function ($a): void {
		}), [new ScratchDirectory('/tmp')]];
		yield 'a variadic parameter takes the rest' => [new ReflectionFunction(static function (int $a, int ...$rest): void {
		}), [1, 2, 3]];
		yield 'a variadic parameter takes nothing' => [new ReflectionFunction(static function (int $a, int ...$rest): void {
		}), [1]];
		yield 'a union with a service type is data' => [new ReflectionFunction(static function (Meter|int $a): void {
		}), [1]];
	}

	/**
	 * @param list<mixed> $arguments
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('dataResolve')]
	public function testResolve(ReflectionFunction $function, array $arguments): void
	{
		$this->assertSame($arguments, (new ArgumentResolver())->resolve($function, $arguments));
	}

	/**
	 * @return iterable<string, array{ReflectionMethod, list<mixed>, string}>
	 */
	public static function dataInvalid(): iterable
	{
		$method = self::class . '::method(): ';
		yield 'too few' => [new ReflectionMethod(self::class, 'method'), [1], $method . 'the data set has 1 argument, but parameter $b needs one too.'];
		yield 'none' => [new ReflectionMethod(self::class, 'method'), [], $method . 'the data set has 0 arguments, but parameter $a needs one too.'];
		yield 'too many' => [new ReflectionMethod(self::class, 'method'), [1, 'b', 3], $method . 'the data set has 3 arguments, but the method takes 2.'];
		yield 'far too many' => [new ReflectionMethod(self::class, 'method'), [1, 'b', 3, 4], $method . 'the data set has 4 arguments, but the method takes 2.'];
		$withMeter = self::class . '::withMeter(): ';
		yield 'a Meter parameter' => [new ReflectionMethod(self::class, 'withMeter'), [1], $withMeter . 'parameter $meter is a Meter. Take it in the constructor of the class, a method gets only the arguments of its data set.'];
		$withScratch = self::class . '::withScratch(): ';
		yield 'a nullable ScratchDirectory parameter' => [new ReflectionMethod(self::class, 'withScratch'), [], $withScratch . 'parameter $scratch is a ScratchDirectory. Take it in the constructor of the class, a method gets only the arguments of its data set.'];
	}

	/**
	 * @param list<mixed> $arguments
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('dataInvalid')]
	public function testInvalid(ReflectionMethod $method, array $arguments, string $message): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage($message);

		(new ArgumentResolver())->resolve($method, $arguments);
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testAHookTakesTheLeadingArguments(): void
	{
		$this->assertSame([1, 'b'], (new ArgumentResolver())->resolveHook(new ReflectionMethod($this, 'method'), [1, 'b', 3]));
	}

	/**
	 * @return iterable<string, array{ReflectionMethod, list<mixed>, string}>
	 */
	public static function dataInvalidHook(): iterable
	{
		yield 'too few' => [new ReflectionMethod(self::class, 'method'), [1], self::class . '::method(): the data set has 1 argument, but parameter $b needs one too.'];
		yield 'a Meter parameter' => [new ReflectionMethod(self::class, 'withMeter'), [1, 2], self::class . '::withMeter(): parameter $meter is a Meter.'];
	}

	/**
	 * @param list<mixed> $arguments
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('dataInvalidHook')]
	public function testInvalidHook(ReflectionMethod $method, array $arguments, string $message): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage($message);

		(new ArgumentResolver())->resolveHook($method, $arguments);
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testAClosureIsNamedAsSuch(): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage('{closure}(): the data set has 1 argument, but the method takes 0.');

		(new ArgumentResolver())->resolve(new ReflectionFunction(static function (): void {
		}), [1]);
	}

	public function method(int $a, string $b): void
	{
	}

	public function withMeter(int $a, Meter $meter): void
	{
	}

	public function withScratch(?ScratchDirectory $scratch): void
	{
	}

}
