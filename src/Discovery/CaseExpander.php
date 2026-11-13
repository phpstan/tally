<?php declare(strict_types = 1);

namespace PHPStan\Tally\Discovery;

use PHPStan\Tally\Attribute\AfterMethods;
use PHPStan\Tally\Attribute\BeforeMethods;
use PHPStan\Tally\Attribute\DataProvider;
use PHPStan\Tally\Attribute\Expected;
use PHPStan\Tally\Attribute\Warmup;
use PHPStan\Tally\BenchmarkCase;
use PHPStan\Tally\CaseId;
use PHPStan\Tally\DataSet;
use PHPStan\Tally\Expectation\AttributeExpectations;
use PHPStan\Tally\Metrics;
use PHPStan\Tally\Quantity\InvalidQuantityException;
use ReflectionClass;
use ReflectionMethod;
use function array_key_exists;
use function array_keys;
use function count;
use function is_array;
use function is_int;
use function is_iterable;
use function is_string;
use function sprintf;

/**
 * Turns one benchmark method into its cases: one per data set of its data provider, or one
 * when it has none, each with the options its attributes and its class's attributes give.
 */
final class CaseExpander
{

	/**
	 * @param ReflectionClass<object> $class
	 * @return list<BenchmarkCase> in the order the data provider yields them
	 * @throws InvalidBenchmarkException
	 */
	public function expand(ReflectionClass $class, ReflectionMethod $method): array
	{
		$where = $class->getName() . '::' . $method->getName() . '()';
		$beforeMethods = $this->hookMethods($class, $method, BeforeMethods::class, 'before', $where);
		$afterMethods = $this->hookMethods($class, $method, AfterMethods::class, 'after', $where);
		$warmup = $this->warmup($class, $method, $where);
		$expected = $this->expected($method, $where);
		$file = (string) $method->getDeclaringClass()->getFileName();
		$declaringClass = $method->getDeclaringClass()->getName();

		$caseFor = static fn (?string $dataSet, array $arguments, ?Metrics $metrics) => new BenchmarkCase(
			new CaseId($class->getName(), $method->getName(), $dataSet),
			$arguments,
			$warmup,
			$beforeMethods,
			$afterMethods,
			new AttributeExpectations($file, $declaringClass, $method->getName(), $dataSet, $metrics),
		);

		$provider = $this->dataSets($class, $method, $where);
		if ($provider === null) {
			foreach ($expected as [$dataSet]) {
				if ($dataSet !== null) {
					throw new InvalidBenchmarkException($where, sprintf('#[Expected] names data set %s, but the method has no data provider.', $dataSet));
				}
			}
			if (count($expected) > 1) {
				throw new InvalidBenchmarkException($where, 'it has more than one #[Expected] attribute.');
			}

			return [$caseFor(null, [], $expected[0][1] ?? null)];
		}

		[$providerName, $dataSets] = $provider;
		$expectedByDataSet = [];
		foreach ($expected as [$dataSet, $metrics]) {
			if ($dataSet === null) {
				throw new InvalidBenchmarkException($where, '#[Expected] needs dataSet, because the method has a data provider.');
			}
			$expectedByDataSet[$dataSet] = $metrics;
		}

		$cases = [];
		$yielded = [];
		foreach ($dataSets as $dataSet) {
			$yielded[$dataSet->name] = true;
			$metrics = $expectedByDataSet[$dataSet->name] ?? null;
			if ($dataSet->expectations === null) {
				$cases[] = $caseFor($dataSet->name, $dataSet->arguments, $metrics);
				continue;
			}
			if ($metrics !== null) {
				throw new InvalidBenchmarkException($where, sprintf('data set %s has its own expectations and an #[Expected] attribute.', $dataSet->name));
			}

			$cases[] = new BenchmarkCase(
				new CaseId($class->getName(), $method->getName(), $dataSet->name),
				$dataSet->arguments,
				$warmup,
				$beforeMethods,
				$afterMethods,
				$dataSet->expectations,
			);
		}

		foreach (array_keys($expectedByDataSet) as $dataSet) {
			if (!array_key_exists($dataSet, $yielded)) {
				throw new InvalidBenchmarkException($where, sprintf('#[Expected] names data set %s, which %s() does not yield.', $dataSet, $providerName));
			}
		}

		return $cases;
	}

	/**
	 * @param ReflectionClass<object> $class
	 * @param class-string<BeforeMethods|AfterMethods> $attribute
	 * @return list<string>
	 * @throws InvalidBenchmarkException
	 */
	private function hookMethods(ReflectionClass $class, ReflectionMethod $method, string $attribute, string $kind, string $where): array
	{
		$names = [];
		foreach ([...$class->getAttributes($attribute), ...$method->getAttributes($attribute)] as $reflectionAttribute) {
			foreach ($reflectionAttribute->newInstance()->methods as $name) {
				if (!$class->hasMethod($name)) {
					throw new InvalidBenchmarkException($where, sprintf('%s method %s() does not exist.', $kind, $name));
				}
				if (!$class->getMethod($name)->isPublic()) {
					throw new InvalidBenchmarkException($where, sprintf('%s method %s() must be public.', $kind, $name));
				}
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * @param ReflectionClass<object> $class
	 * @throws InvalidBenchmarkException
	 */
	private function warmup(ReflectionClass $class, ReflectionMethod $method, string $where): int
	{
		$attributes = $method->getAttributes(Warmup::class);
		if ($attributes === []) {
			$attributes = $class->getAttributes(Warmup::class);
		}
		$calls = $attributes === [] ? 0 : $attributes[0]->newInstance()->calls;
		if ($calls < 0) {
			throw new InvalidBenchmarkException($where, '#[Warmup] needs zero or more calls.');
		}

		return $calls;
	}

	/**
	 * @return list<array{string|null, Metrics}>
	 * @throws InvalidBenchmarkException
	 */
	private function expected(ReflectionMethod $method, string $where): array
	{
		$expected = [];
		foreach ($method->getAttributes(Expected::class) as $attribute) {
			$instance = $attribute->newInstance();
			try {
				$expected[] = [$instance->dataSet, $instance->metrics()];
			} catch (InvalidQuantityException $e) {
				throw new InvalidBenchmarkException($where, $e->getMessage());
			}
		}

		return $expected;
	}

	/**
	 * @param ReflectionClass<object> $class
	 * @return array{string, list<DataSet>}|null the name of the data provider and its data sets
	 * @throws InvalidBenchmarkException
	 */
	private function dataSets(ReflectionClass $class, ReflectionMethod $method, string $where): ?array
	{
		$attributes = $method->getAttributes(DataProvider::class);
		if ($attributes === []) {
			return null;
		}

		$name = $attributes[0]->newInstance()->method;
		if (!$class->hasMethod($name)) {
			throw new InvalidBenchmarkException($where, sprintf('data provider %s() does not exist.', $name));
		}
		$provider = $class->getMethod($name);
		if (!$provider->isPublic() || !$provider->isStatic()) {
			throw new InvalidBenchmarkException($where, sprintf('data provider %s() must be public and static.', $name));
		}
		$values = $provider->invoke(null);
		if (!is_iterable($values)) {
			throw new InvalidBenchmarkException($where, sprintf('data provider %s() must return an iterable.', $name));
		}

		$dataSets = [];
		$names = [];
		foreach ($values as $key => $value) {
			if ($value instanceof DataSet) {
				$dataSet = $value;
			} else {
				$dataSetName = is_int($key) ? '#' . $key : (is_string($key) ? $key : '');
				if (!is_array($value)) {
					throw new InvalidBenchmarkException($where, sprintf('data set %s of %s() is neither an array of arguments nor a DataSet.', $dataSetName, $name));
				}
				$dataSet = DataSet::named($dataSetName, $value);
			}

			if (isset($names[$dataSet->name])) {
				throw new InvalidBenchmarkException($where, sprintf('data provider %s() yields data set %s twice.', $name, $dataSet->name));
			}
			$names[$dataSet->name] = true;
			$dataSets[] = $dataSet;
		}

		return [$name, $dataSets];
	}

}
