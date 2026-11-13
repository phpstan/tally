<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Expression;

use Closure;
use Countable;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;
use stdClass;
use Tally\Expression\ValueType;

final class ValueTypeTest extends TestCase
{

	/**
	 * @return iterable<string, array{Closure(never): void, ValueType}>
	 */
	public static function dataOfReflection(): iterable
	{
		yield 'no type' => [static function ($x): void {
		}, ValueType::Mixed];
		yield 'int' => [static function (int $x): void {
		}, ValueType::Number];
		yield 'float' => [static function (float $x): void {
		}, ValueType::Number];
		yield 'int or float' => [static function (int|float $x): void {
		}, ValueType::Number];
		yield 'array' => [static function (array $x): void {
		}, ValueType::Array];
		yield 'string' => [static function (string $x): void {
		}, ValueType::String];
		yield 'bool' => [static function (bool $x): void {
		}, ValueType::Bool];
		yield 'true' => [static function (true $x): void {
		}, ValueType::Bool];
		yield 'false' => [static function (false $x): void {
		}, ValueType::Bool];
		yield 'null' => [static function (null $x): void {
		}, ValueType::Null];
		yield 'mixed' => [static function (mixed $x): void {
		}, ValueType::Mixed];
		yield 'iterable' => [static function (iterable $x): void {
		}, ValueType::Mixed];
		yield 'a class' => [static function (stdClass $x): void {
		}, ValueType::Object];
		yield 'nullable' => [static function (?int $x): void {
		}, ValueType::Mixed];
		yield 'int or null' => [static function (int|null $x): void {
		}, ValueType::Mixed];
		yield 'int or string' => [static function (int|string $x): void {
		}, ValueType::Mixed];
		yield 'array or string' => [static function (array|string $x): void {
		}, ValueType::Mixed];
		yield 'an intersection' => [static function (Countable&Iterator $x): void {
		}, ValueType::Object];
		yield 'two classes' => [static function (stdClass|Closure $x): void {
		}, ValueType::Object];
	}

	/**
	 * @param Closure(never): void $function
	 */
	#[DataProvider('dataOfReflection')]
	public function testOfReflection(Closure $function, ValueType $type): void
	{
		$this->assertSame($type, ValueType::ofReflection((new ReflectionParameter($function, 0))->getType()));
	}

	/**
	 * @return iterable<string, array{mixed, ValueType}>
	 */
	public static function dataOfValue(): iterable
	{
		yield 'int' => [1, ValueType::Number];
		yield 'float' => [1.5, ValueType::Number];
		yield 'array' => [[], ValueType::Array];
		yield 'string' => ['1', ValueType::String];
		yield 'bool' => [false, ValueType::Bool];
		yield 'null' => [null, ValueType::Null];
		yield 'object' => [new stdClass(), ValueType::Object];
	}

	#[DataProvider('dataOfValue')]
	public function testOfValue(mixed $value, ValueType $type): void
	{
		$this->assertSame($type, ValueType::ofValue($value));
	}

	/**
	 * @return iterable<string, array{ValueType, ValueType, bool}>
	 */
	public static function dataMayBe(): iterable
	{
		yield 'the same type' => [ValueType::Number, ValueType::Number, true];
		yield 'another type' => [ValueType::Array, ValueType::Number, false];
		yield 'mixed may be anything' => [ValueType::Mixed, ValueType::Number, true];
		yield 'nothing else may be mixed' => [ValueType::Number, ValueType::Mixed, false];
	}

	#[DataProvider('dataMayBe')]
	public function testMayBe(ValueType $type, ValueType $other, bool $mayBe): void
	{
		$this->assertSame($mayBe, $type->mayBe($other));
	}

}
