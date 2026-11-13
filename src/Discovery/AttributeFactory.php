<?php declare(strict_types = 1);

namespace Tally\Discovery;

use Attribute;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionParameter;
use Throwable;
use function array_key_exists;
use function array_keys;
use function array_pop;
use function array_values;
use function count;
use function get_debug_type;
use function implode;
use function is_int;
use function sprintf;
use function strrchr;
use function substr;

/**
 * Creates an attribute of a benchmark after checking its arguments against the attribute's
 * constructor, so that a mistake in them says which argument is wrong instead of failing
 * with a PHP error. Arguments are checked as under strict_types.
 */
final class AttributeFactory
{

	public function __construct(private ValueTypeCheck $typeCheck)
	{
	}

	/**
	 * @template T of object
	 * @param ReflectionAttribute<T> $attribute
	 * @param string $where the benchmark the attribute belongs to
	 * @return T
	 * @throws InvalidBenchmarkException
	 */
	public function create(ReflectionAttribute $attribute, string $where): object
	{
		$class = new ReflectionClass($attribute->getName());
		$name = '#[' . $this->shortName($class->getName()) . ']';
		if ($attribute->isRepeated() && !$this->isRepeatable($class)) {
			throw new InvalidBenchmarkException($where, sprintf('%s can be used only once.', $name));
		}

		$this->checkArguments($name, $class->getConstructor()?->getParameters() ?? [], $attribute->getArguments(), $where);

		try {
			return $attribute->newInstance();
		} catch (Throwable $e) {
			// the attribute's own check of its values, such as a negative tolerance
			throw new InvalidBenchmarkException($where, $e->getMessage());
		}
	}

	/**
	 * @param list<ReflectionParameter> $parameters
	 * @param array<int|string, mixed> $arguments positional ones under ints, named ones under their names
	 * @throws InvalidBenchmarkException
	 */
	private function checkArguments(string $name, array $parameters, array $arguments, string $where): void
	{
		$variadic = null;
		$named = [];
		foreach ($parameters as $parameter) {
			if ($parameter->isVariadic()) {
				$variadic = $parameter;
			} else {
				$named[$parameter->getName()] = $parameter;
			}
		}
		$positional = array_values($named);

		$given = [];
		foreach ($arguments as $key => $value) {
			$parameter = is_int($key) ? ($positional[$key] ?? $variadic) : ($named[$key] ?? $variadic);
			if ($parameter === null) {
				throw new InvalidBenchmarkException($where, is_int($key)
					? sprintf('%s takes %s, %d given.', $name, $this->count(count($parameters)), count($arguments))
					: sprintf('%s has no argument %s, %s.', $name, $key, $named === [] ? 'it takes no arguments' : 'only ' . $this->list(array_keys($named))));
			}
			if (!$parameter->isVariadic()) {
				if (array_key_exists($parameter->getName(), $given)) {
					throw new InvalidBenchmarkException($where, sprintf('%s gets argument %s twice.', $name, $parameter->getName()));
				}
				$given[$parameter->getName()] = true;
			}
			if (!$this->typeCheck->accepts($parameter->getType(), $value)) {
				throw new InvalidBenchmarkException($where, sprintf('%s argument %s must be %s, %s given.', $name, $parameter->getName(), (string) $parameter->getType(), get_debug_type($value)));
			}
		}

		$missing = [];
		foreach ($named as $parameterName => $parameter) {
			if ($parameter->isOptional() || array_key_exists($parameterName, $given)) {
				continue;
			}
			$missing[] = $parameterName;
		}
		if ($missing !== []) {
			throw new InvalidBenchmarkException($where, sprintf('%s needs %s %s.', $name, count($missing) === 1 ? 'argument' : 'arguments', $this->list($missing)));
		}
	}

	/**
	 * @param ReflectionClass<object> $class
	 */
	private function isRepeatable(ReflectionClass $class): bool
	{
		$flags = ($class->getAttributes(Attribute::class)[0] ?? null)?->newInstance()->flags ?? Attribute::TARGET_ALL;

		return ($flags & Attribute::IS_REPEATABLE) !== 0;
	}

	private function shortName(string $className): string
	{
		$backslash = strrchr($className, '\\');

		return $backslash === false ? $className : substr($backslash, 1);
	}

	private function count(int $count): string
	{
		return match ($count) {
			0 => 'no arguments',
			1 => '1 argument',
			default => $count . ' arguments',
		};
	}

	/**
	 * @param non-empty-list<string> $names
	 */
	private function list(array $names): string
	{
		$last = array_pop($names);

		return $names === [] ? $last : implode(', ', $names) . ' and ' . $last;
	}

}
