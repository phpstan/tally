<?php declare(strict_types = 1);

namespace Tally\Expression;

use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * What the type checker knows about the value of an expression. The value of a case is the
 * description an error message uses.
 */
enum ValueType: string
{

	case Number = 'a number';
	case Array = 'an array';
	case String = 'a string';
	case Bool = 'a bool';
	case Null = 'null';
	case Object = 'an object';

	/** Anything: the checker reports nothing about it and leaves it to the evaluator */
	case Mixed = 'mixed';

	/** The type of a parameter, Mixed when it may hold values of different types */
	public static function ofReflection(?ReflectionType $type): self
	{
		if ($type instanceof ReflectionUnionType) {
			$union = null;
			foreach ($type->getTypes() as $member) {
				$memberType = self::ofReflection($member);
				if ($union !== null && $union !== $memberType) {
					return self::Mixed;
				}

				$union = $memberType;
			}

			return $union ?? self::Mixed;
		}
		if ($type instanceof ReflectionIntersectionType) {
			return self::Object;
		}
		if (!$type instanceof ReflectionNamedType || ($type->allowsNull() && $type->getName() !== 'null')) {
			return self::Mixed;
		}

		return match ($type->getName()) {
			'int', 'float' => self::Number,
			'array' => self::Array,
			'string' => self::String,
			'bool', 'true', 'false' => self::Bool,
			'null' => self::Null,
			// mixed allows null, so it is Mixed already
			'iterable' => self::Mixed,
			default => self::Object,
		};
	}

	public static function ofValue(mixed $value): self
	{
		return match (true) {
			is_int($value), is_float($value) => self::Number,
			is_array($value) => self::Array,
			is_string($value) => self::String,
			is_bool($value) => self::Bool,
			$value === null => self::Null,
			default => self::Object,
		};
	}

	/** Whether a value of this type may be of the other type */
	public function mayBe(self $other): bool
	{
		return $this === $other || $this === self::Mixed;
	}

}
