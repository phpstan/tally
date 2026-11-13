<?php declare(strict_types = 1);

namespace Tally\Runner;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use Tally\BenchmarkCase;
use Tally\Discovery\ArgumentResolver;
use Tally\Discovery\ConstructorArguments;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Metrics;
use Tally\ScratchDirectory;
use Throwable;
use function class_exists;
use function sprintf;

/**
 * Runs one case in this process: creates the benchmark object, calls the before methods and the
 * warm-up calls uncounted, counts an empty call as the baseline and then one call of the benchmark,
 * and calls the after methods, also when the benchmark throws.
 *
 * The first counted span costs more than later ones: PHP does work once on the first call of the
 * counting path, and the caches CodSpeed simulates start cold. Under the simulation, the first
 * baseline counted about twice the instructions and cycles of the second one. So the baseline is
 * counted twice, and only the second one, named after the case with #baseline, is subtracted.
 *
 * The baseline is a separate part of the profile. It goes
 * through the same steps as the counted call: the return from starting the counter, a closure
 * call with the resolved arguments, and stopping the counter, so subtracting it leaves the cost
 * of the benchmark method alone.
 *
 * The object gets one MeterSession for the whole case: every call is a span of it, the hooks and
 * warm-up calls with a counter that counts nothing. Its memory is subtracted here, its instructions and cycles
 * from CodSpeed's profile.
 */
final class CaseRunner
{

	public const BASELINE_SUFFIX = '#baseline';

	/** The part counted before the baseline and left out */
	public const WARMUP_SUFFIX = '#warmup';

	public function __construct(
		private ConstructorArguments $constructor,
		private ArgumentResolver $resolver,
		private Memory $memory,
		private IdleCounter $uncounted
	)
	{
	}

	/**
	 * @return Metrics the memory of the counted call above the baseline; the counter's tool reports the rest
	 * @throws InvalidBenchmarkException
	 * @throws Throwable what the benchmark throws
	 */
	public function run(BenchmarkCase $case, ScratchDirectory $scratch, Counter $counter): Metrics
	{
		$id = $case->id->toString();
		if (!class_exists($case->id->class)) {
			throw new InvalidBenchmarkException($id, sprintf('class %s does not exist.', $case->id->class));
		}
		$class = new ReflectionClass($case->id->class);
		$meter = new MeterSession($this->memory);
		$object = $class->newInstanceArgs($this->constructor->resolve($class, $meter, $scratch));
		$benchmark = $class->getMethod($case->id->method);
		$arguments = $this->resolver->resolve($benchmark, $case->arguments);
		$call = $benchmark->getClosure($object);

		try {
			foreach ($case->beforeMethods as $name) {
				$this->callHook($meter, $object, $class->getMethod($name), $case);
			}
			for ($i = 0; $i < $case->warmup; $i++) {
				$this->span($meter, $this->uncounted, $call, $arguments, $benchmark->getName());
			}

			$baseline = $this->baseline(...);
			$this->span($meter, $counter, $baseline, $arguments, $id . self::WARMUP_SUFFIX);
			$baselineMemory = $this->span($meter, $counter, $baseline, $arguments, $id . self::BASELINE_SUFFIX);

			return $this->span($meter, $counter, $call, $arguments, $id)->minus($baselineMemory);
		} finally {
			foreach ($case->afterMethods as $name) {
				$this->callHook($meter, $object, $class->getMethod($name), $case);
			}
		}
	}

	/** What the baseline counts instead of the benchmark method */
	private function baseline(mixed ...$arguments): void
	{
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	private function callHook(MeterSession $meter, object $object, ReflectionMethod $method, BenchmarkCase $case): void
	{
		$this->span($meter, $this->uncounted, $method->getClosure($object), $this->resolver->resolveHook($method, $case->arguments), $method->getName());
	}

	/**
	 * Calls the closure as one span of the meter.
	 *
	 * @param Closure(mixed...): mixed $call
	 * @param list<mixed> $arguments
	 * @return Metrics the memory
	 */
	private function span(MeterSession $meter, Counter $counter, Closure $call, array $arguments, string $part): Metrics
	{
		$meter->start($counter);
		$call(...$arguments);

		return $meter->stop($part);
	}

}
