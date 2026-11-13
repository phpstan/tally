<?php declare(strict_types = 1);

namespace Tally\Discovery;

use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use function get_debug_type;
use function is_callable;
use function is_float;
use function is_int;
use function is_iterable;
use function is_object;

/**
 * Whether a parameter type accepts a value as it does under strict_types: an int is accepted
 * for a float, nothing else is converted.
 */
final class ValueTypeCheck
{

	/**
	 * @param ReflectionType|null $type null for a parameter without a type
	 */
	public function accepts(?ReflectionType $type, mixed $value): bool
	{
		if ($type === null) {
			return true;
		}
		if ($value === null) {
			return $type->allowsNull();
		}
		if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
			// a union accepts what one of its members does, an intersection what all of them do
			$union = $type instanceof ReflectionUnionType;
			foreach ($type->getTypes() as $member) {
				if ($this->accepts($member, $value) === $union) {
					return $union;
				}
			}

			return !$union;
		}

		return $type instanceof ReflectionNamedType && $this->acceptsNamed($type, $value);
	}

	private function acceptsNamed(ReflectionNamedType $type, mixed $value): bool
	{
		$name = $type->getName();
		if (!$type->isBuiltin()) {
			return $value instanceof $name;
		}

		return match ($name) {
			'mixed' => true,
			'float' => is_float($value) || is_int($value),
			'true' => $value === true,
			'false' => $value === false,
			'iterable' => is_iterable($value),
			'object' => is_object($value),
			'callable' => is_callable($value),
			// int, string, bool, array
			default => get_debug_type($value) === $name,
		};
	}

}
