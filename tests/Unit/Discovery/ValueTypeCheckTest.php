<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Discovery;

use ArrayAccess;
use ArrayObject;
use Closure;
use Countable;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionType;
use stdClass;
use Tally\Discovery\ValueTypeCheck;
use Tally\Metric;

final class ValueTypeCheckTest extends TestCase
{

	/**
	 * @return iterable<string, array{Closure(never): mixed, mixed, bool}> a closure whose parameter has the type, the value, and whether the type accepts it
	 */
	public static function dataAccepts(): iterable
	{
		yield 'int, an int' => [static fn (int $x) => $x, 1, true];
		yield 'int, a numeric string' => [static fn (int $x) => $x, '1', false];
		yield 'int, a float' => [static fn (int $x) => $x, 1.0, false];
		yield 'float, a float' => [static fn (float $x) => $x, 1.5, true];
		yield 'float, an int' => [static fn (float $x) => $x, 1, true];
		yield 'float, a string' => [static fn (float $x) => $x, '1.5', false];
		yield 'string, a string' => [static fn (string $x) => $x, 'a', true];
		yield 'string, an int' => [static fn (string $x) => $x, 1, false];
		yield 'bool, false' => [static fn (bool $x) => $x, false, true];
		yield 'bool, an int' => [static fn (bool $x) => $x, 0, false];
		yield 'true, true' => [static fn (true $x) => $x, true, true];
		yield 'true, false' => [static fn (true $x) => $x, false, false];
		yield 'false, false' => [static fn (false $x) => $x, false, true];
		yield 'false, true' => [static fn (false $x) => $x, true, false];
		yield 'array, an array' => [static fn (array $x) => $x, [1], true];
		yield 'array, an object' => [static fn (array $x) => $x, new ArrayObject(), false];
		yield 'iterable, an array' => [static fn (iterable $x) => $x, [], true];
		yield 'iterable, a string' => [static fn (iterable $x) => $x, 'a', false];
		yield 'object, an object' => [static fn (object $x) => $x, new stdClass(), true];
		yield 'object, an array' => [static fn (object $x) => $x, [], false];
		yield 'callable, a function name' => [static fn (callable $x) => $x, 'strlen', true];
		yield 'callable, another string' => [static fn (callable $x) => $x, 'no such function', false];
		yield 'mixed, anything' => [static fn (mixed $x) => $x, [1], true];
		yield 'mixed, null' => [static fn (mixed $x) => $x, null, true];
		yield 'a class, its instance' => [static fn (Countable $x) => $x, new ArrayObject(), true];
		yield 'a class, another object' => [static fn (Countable $x) => $x, new stdClass(), false];
		yield 'an enum, its case' => [static fn (Metric $x) => $x, Metric::Memory, true];
		yield 'an enum, its value' => [static fn (Metric $x) => $x, 'memory', false];
		yield 'not nullable, null' => [static fn (int $x) => $x, null, false];
		yield 'nullable, null' => [static fn (?int $x) => $x, null, true];
		yield 'nullable, the type' => [static fn (?int $x) => $x, 1, true];
		yield 'nullable, another type' => [static fn (?int $x) => $x, 'a', false];
		yield 'a union, its first type' => [static fn (int|string $x) => $x, 1, true];
		yield 'a union, its second type' => [static fn (int|string $x) => $x, 'a', true];
		yield 'a union, neither' => [static fn (int|string $x) => $x, 1.5, false];
		yield 'a union with null, null' => [static fn (int|string|null $x) => $x, null, true];
		yield 'a union without null, null' => [static fn (int|string $x) => $x, null, false];
		yield 'an intersection, both' => [static fn (Countable&ArrayAccess $x) => $x, new ArrayObject(), true];
		yield 'an intersection, one' => [static fn (Countable&ArrayAccess $x) => $x, new class implements Countable {

			#[Override]
			public function count(): int
			{
				return 0;
			}

		}, false];
		yield 'no type, anything' => [static fn ($x) => $x, 'a', true];
	}

	/**
	 * @param Closure(never): mixed $function
	 */
	#[DataProvider('dataAccepts')]
	public function testAccepts(Closure $function, mixed $value, bool $accepts): void
	{
		$this->assertSame($accepts, (new ValueTypeCheck())->accepts(self::type($function), $value));
	}

	/**
	 * @param Closure(never): mixed $function
	 */
	private static function type(Closure $function): ?ReflectionType
	{
		return ((new ReflectionFunction($function))->getParameters()[0] ?? throw new LogicException('The function has no parameter.'))->getType();
	}

}
