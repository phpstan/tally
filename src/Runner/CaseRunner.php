<?php declare(strict_types = 1);

namespace Tally\Runner;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use Tally\BenchmarkCase;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Meter;
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
 * The baseline is a separate part of the profile, named after the case with #baseline. It goes
 * through the same steps as the counted call: the return from starting the counter, a closure
 * call with the resolved arguments, and stopping the counter, so subtracting it leaves the cost
 * of the benchmark method alone.
 */
final class CaseRunner
{

	public const BASELINE_SUFFIX = '#baseline';

	public function __construct(
		private ArgumentResolver $resolver,
		private Memory $memory,
		private IdleCounter $uncounted
	)
	{
	}

	/**
	 * @return Metrics the memory of the counted call; the counter's tool reports the rest
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
		$object = $class->newInstance();
		$benchmark = $class->getMethod($case->id->method);

		try {
			foreach ($case->beforeMethods as $name) {
				$this->callHook($object, $class->getMethod($name), $case, $scratch);
			}
			for ($i = 0; $i < $case->warmup; $i++) {
				$this->callUncounted($object, $benchmark, fn (Meter $meter): array => $this->resolver->resolve($benchmark, $case->arguments, $meter, $scratch));
			}

			$baseline = $this->baseline(...);
			$session = new MeterSession($counter, $this->memory);
			$arguments = $this->resolver->resolve($benchmark, $case->arguments, $session, $scratch);
			$session->start();
			$baseline(...$arguments);
			$session->stop($id . self::BASELINE_SUFFIX);

			$call = $benchmark->getClosure($object);
			$session = new MeterSession($counter, $this->memory);
			$arguments = $this->resolver->resolve($benchmark, $case->arguments, $session, $scratch);
			$session->start();
			$call(...$arguments);

			return $session->stop($id);
		} finally {
			foreach ($case->afterMethods as $name) {
				$this->callHook($object, $class->getMethod($name), $case, $scratch);
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
	private function callHook(object $object, ReflectionMethod $method, BenchmarkCase $case, ScratchDirectory $scratch): void
	{
		$this->callUncounted($object, $method, fn (Meter $meter): array => $this->resolver->resolveHook($method, $case->arguments, $meter, $scratch));
	}

	/**
	 * @param Closure(Meter): list<mixed> $resolve
	 * @throws InvalidBenchmarkException
	 */
	private function callUncounted(object $object, ReflectionMethod $method, Closure $resolve): void
	{
		$session = new MeterSession($this->uncounted, $this->memory);
		$arguments = $resolve($session);
		$session->start();
		$method->invokeArgs($object, $arguments);
		$session->stop($method->getName());
	}

}
