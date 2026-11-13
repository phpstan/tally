<?php declare(strict_types = 1);

namespace Tally\Discovery;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Tally\Attribute\After;
use Tally\Attribute\Before;
use Tally\Attribute\DataProvider;
use Tally\Attribute\Expected;
use Tally\Attribute\ExpectedFor;
use Tally\Attribute\RelativeTolerance;
use Tally\Attribute\Warmup;
use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Comparison\TolerancePolicy;
use Tally\Complexity\ComplexityClass;
use Tally\Complexity\GrowthPoint;
use Tally\DataSet;
use Tally\Expectation\AttributeExpectations;
use Tally\Expectation\ExpectationSource;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Metrics;
use Tally\Quantity\InvalidQuantityException;
use Throwable;
use function array_map;
use function count;
use function implode;
use function in_array;
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

	/** The share each metric may differ by in a benchmark without a tolerance policy */
	public const DEFAULT_TOLERANCE = 0.1;

	public function __construct(
		private ExpectedAttributeRewriter $rewriter,
		private AttributeFactory $attributes,
		private ArgumentResolver $resolver,
		private GrowthPoints $growthPoints
	)
	{
	}

	/**
	 * The #[Before] and #[After] methods of the class, in declaration order. They are the same for
	 * every benchmark of the class, so they are found once per class, not once per benchmark.
	 *
	 * @param ReflectionClass<object> $class
	 * @return array{before: list<string>, after: list<string>}
	 */
	public function hooks(ReflectionClass $class): array
	{
		$hooks = ['before' => [], 'after' => []];
		foreach ($class->getMethods() as $method) {
			if ($method->getAttributes(Before::class) !== []) {
				$hooks['before'][] = $method->getName();
			}
			if ($method->getAttributes(After::class) === []) {
				continue;
			}

			$hooks['after'][] = $method->getName();
		}

		return $hooks;
	}

	/**
	 * @param ReflectionClass<object> $class
	 * @param array{before: list<string>, after: list<string>} $hooks what hooks() returns for the class
	 * @return list<BenchmarkCase> in the order the data provider yields them
	 * @throws InvalidBenchmarkException
	 */
	public function expand(ReflectionClass $class, ReflectionMethod $method, array $hooks): array
	{
		$where = $class->getName() . '::' . $method->getName() . '()';
		foreach ([Before::class => 'Before', After::class => 'After'] as $hook => $name) {
			if ($method->getAttributes($hook) !== []) {
				throw new InvalidBenchmarkException($where, sprintf('a benchmark method cannot be a #[%s] method, give the hook a name that does not start with bench.', $name));
			}
		}
		$warmup = $this->warmup($class, $method, $where);
		$tolerance = $this->tolerance($class, $method, $where);
		$expected = $this->expected($method, $where);
		$expectedFor = $this->expectedFor($method, $where);
		$complexity = $this->complexity($method, $where);
		$file = (string) $method->getDeclaringClass()->getFileName();
		$declaringClass = $method->getDeclaringClass()->getName();

		$caseFor = fn (?string $dataSet, array $arguments, ?Metrics $metrics) => $this->case(
			$class,
			$method,
			$dataSet,
			$arguments,
			$warmup,
			$hooks,
			new AttributeExpectations($this->rewriter, $file, $declaringClass, $method->getName(), $dataSet, $metrics),
			$tolerance,
		);

		$provider = $this->dataSets($class, $method, $where);
		if ($provider === null && $complexity !== null) {
			throw new InvalidBenchmarkException($where, sprintf('%s compares data sets of different sizes, but the method has no data provider.', $complexity[1]));
		}
		if ($complexity !== null && $expectedFor !== []) {
			throw new InvalidBenchmarkException($where, sprintf('%s checks how the instructions grow and #[ExpectedFor] expects numbers, keep one of them.', $complexity[1]));
		}
		if ($provider === null) {
			foreach ($expectedFor as [$dataSet]) {
				throw new InvalidBenchmarkException($where, sprintf('#[ExpectedFor] names data set %s, but the method has no data provider.', $dataSet));
			}

			return [$caseFor(null, [], $expected)];
		}
		if ($expected !== null) {
			throw new InvalidBenchmarkException($where, '#[Expected] is for a method without a data provider, use #[ExpectedFor] for each data set.');
		}

		[$providerName, $dataSets] = $provider;
		$names = array_map(static fn (DataSet $dataSet): string => $dataSet->name, $dataSets);
		$expectedByDataSet = [];
		foreach ($expectedFor as [$dataSet, $metrics]) {
			if (!in_array($dataSet, $names, true)) {
				throw new InvalidBenchmarkException($where, sprintf('#[ExpectedFor] names data set %s, which %s() does not yield.', $dataSet, $providerName));
			}
			$expectedByDataSet[$dataSet] = $metrics;
		}

		$cases = [];
		foreach ($dataSets as $dataSet) {
			$metrics = $expectedByDataSet[$dataSet->name] ?? null;
			if ($dataSet->expectations === null) {
				$cases[] = $caseFor($dataSet->name, $dataSet->arguments, $metrics);
				continue;
			}
			if ($metrics !== null) {
				throw new InvalidBenchmarkException($where, sprintf('data set %s has expectations from the data provider and from #[ExpectedFor], keep one of them.', $dataSet->name));
			}
			if ($complexity !== null) {
				throw new InvalidBenchmarkException($where, sprintf('data set %s has expectations from the data provider, but %s checks how the instructions grow, keep one of them.', $dataSet->name, $complexity[1]));
			}

			$cases[] = $this->case($class, $method, $dataSet->name, $dataSet->arguments, $warmup, $hooks, $dataSet->expectations, $tolerance);
		}
		if ($complexity === null) {
			return $cases;
		}

		return array_map(
			static fn (BenchmarkCase $case, GrowthPoint $growth): BenchmarkCase => $case->withGrowth($growth),
			$cases,
			$this->growthPoints->points($class->getName(), $method, $complexity[0], $complexity[1], $dataSets, $where),
		);
	}

	/**
	 * A case, after checking that the benchmark method and its hooks take the arguments of its data set.
	 *
	 * @param ReflectionClass<object> $class
	 * @param list<mixed> $arguments
	 * @param array{before: list<string>, after: list<string>} $hooks
	 * @throws InvalidBenchmarkException
	 */
	private function case(
		ReflectionClass $class,
		ReflectionMethod $method,
		?string $dataSet,
		array $arguments,
		int $warmup,
		array $hooks,
		ExpectationSource $expectations,
		TolerancePolicy $tolerance
	): BenchmarkCase
	{
		$this->resolver->resolve($method, $arguments);
		foreach ([...$hooks['before'], ...$hooks['after']] as $hook) {
			$this->resolver->resolveHook($class->getMethod($hook), $arguments);
		}

		return new BenchmarkCase(new CaseId($class->getName(), $method->getName(), $dataSet), $arguments, $warmup, $hooks['before'], $hooks['after'], $expectations, $tolerance, null);
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
		$calls = $attributes === [] ? 0 : $this->attributes->create($attributes[0], $where)->calls;
		if ($calls < 0) {
			throw new InvalidBenchmarkException($where, '#[Warmup] needs zero or more calls.');
		}

		return $calls;
	}

	/**
	 * The policy among the attributes of the method, else of the class, else the default.
	 *
	 * @param ReflectionClass<object> $class
	 * @throws InvalidBenchmarkException
	 */
	private function tolerance(ReflectionClass $class, ReflectionMethod $method, string $where): TolerancePolicy
	{
		foreach ([$method->getAttributes(TolerancePolicy::class, ReflectionAttribute::IS_INSTANCEOF), $class->getAttributes(TolerancePolicy::class, ReflectionAttribute::IS_INSTANCEOF)] as $attributes) {
			if (count($attributes) > 1) {
				$names = array_map(static fn (ReflectionAttribute $attribute): string => $attribute->getName(), $attributes);
				throw new InvalidBenchmarkException($where, 'it has more than one tolerance policy: ' . implode(', ', $names) . '.');
			}
			if ($attributes !== []) {
				return $this->attributes->create($attributes[0], $where);
			}
		}

		return new RelativeTolerance(self::DEFAULT_TOLERANCE);
	}

	/**
	 * @return array{ComplexityClass, string}|null the complexity class and the name of its attribute
	 * @throws InvalidBenchmarkException
	 */
	private function complexity(ReflectionMethod $method, string $where): ?array
	{
		$attributes = $method->getAttributes(ComplexityClass::class, ReflectionAttribute::IS_INSTANCEOF);
		if (count($attributes) > 1) {
			$names = array_map(static fn (ReflectionAttribute $attribute): string => $attribute->getName(), $attributes);
			throw new InvalidBenchmarkException($where, 'it has more than one complexity class: ' . implode(', ', $names) . '.');
		}
		if ($attributes === []) {
			return null;
		}

		return [$this->attributes->create($attributes[0], $where), $this->attributes->name($attributes[0]->getName())];
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	private function expected(ReflectionMethod $method, string $where): ?Metrics
	{
		$attributes = $method->getAttributes(Expected::class);
		if ($attributes === []) {
			return null;
		}

		try {
			return $this->attributes->create($attributes[0], $where)->metrics();
		} catch (InvalidQuantityException $e) {
			throw new InvalidBenchmarkException($where, '#[Expected] argument ' . $e->getMessage());
		}
	}

	/**
	 * @return list<array{string, Metrics}> data set names and their metrics
	 * @throws InvalidBenchmarkException
	 */
	private function expectedFor(ReflectionMethod $method, string $where): array
	{
		$expected = [];
		$names = [];
		foreach ($method->getAttributes(ExpectedFor::class) as $attribute) {
			$instance = $this->attributes->create($attribute, $where);
			if (in_array($instance->dataSet, $names, true)) {
				throw new InvalidBenchmarkException($where, sprintf('#[ExpectedFor] names data set %s twice.', $instance->dataSet));
			}
			$names[] = $instance->dataSet;
			try {
				$expected[] = [$instance->dataSet, $instance->metrics()];
			} catch (InvalidQuantityException $e) {
				throw new InvalidBenchmarkException($where, '#[ExpectedFor] argument ' . $e->getMessage());
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

		$name = $this->attributes->create($attributes[0], $where)->method;
		if (!$class->hasMethod($name)) {
			throw new InvalidBenchmarkException($where, sprintf('data provider %s() does not exist.', $name));
		}
		$provider = $class->getMethod($name);
		if (!$provider->isPublic() || !$provider->isStatic()) {
			throw new InvalidBenchmarkException($where, sprintf('data provider %s() must be public and static.', $name));
		}
		if ($provider->getNumberOfParameters() > 0) {
			throw new InvalidBenchmarkException($where, sprintf('data provider %s() cannot take arguments, Tally calls it without any.', $name));
		}

		try {
			return [$name, $this->yieldedDataSets($provider->invoke(null), $name, $where)];
		} catch (InvalidBenchmarkException $e) {
			throw $e;
		} catch (Throwable $e) {
			throw new InvalidBenchmarkException($where, sprintf('data provider %s() threw %s: %s', $name, $e::class, $e->getMessage()));
		}
	}

	/**
	 * @return list<DataSet>
	 * @throws InvalidBenchmarkException
	 */
	private function yieldedDataSets(mixed $values, string $name, string $where): array
	{
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

			if (in_array($dataSet->name, $names, true)) {
				throw new InvalidBenchmarkException($where, sprintf('data provider %s() yields data set %s twice.', $name, $dataSet->name));
			}
			$names[] = $dataSet->name;
			$dataSets[] = $dataSet;
		}

		return $dataSets;
	}

}
