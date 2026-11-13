<?php declare(strict_types = 1);

namespace Tally\Discovery;

use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Tally\Meter;
use Tally\ScratchDirectory;
use function array_shift;
use function count;
use function in_array;
use function sprintf;

/**
 * Fills the parameters of a benchmark, before, or after method with the arguments of the data
 * set, in order. Default values of parameters are not used, the data set passes every argument.
 * Meter and ScratchDirectory come through the constructor of the class, not as arguments.
 */
final class ArgumentResolver
{

	/** The types the constructor of a benchmark class takes */
	private const SERVICES = [Meter::class, ScratchDirectory::class];

	/**
	 * @param list<mixed> $arguments the data set's
	 * @return list<mixed>
	 * @throws InvalidBenchmarkException
	 */
	public function resolve(ReflectionFunctionAbstract $function, array $arguments): array
	{
		[$resolved, $remaining] = $this->fill($function, $arguments);
		if ($remaining !== []) {
			throw new InvalidBenchmarkException($this->name($function), sprintf(
				'the data set has %s, but the method takes %d.',
				$this->arguments(count($arguments)),
				$function->getNumberOfParameters(),
			));
		}

		return $resolved;
	}

	/**
	 * Like resolve(), for before and after methods, which take only as many of the data set's
	 * arguments as they declare, from the first one.
	 *
	 * @param list<mixed> $arguments the data set's
	 * @return list<mixed>
	 * @throws InvalidBenchmarkException
	 */
	public function resolveHook(ReflectionFunctionAbstract $function, array $arguments): array
	{
		return $this->fill($function, $arguments)[0];
	}

	/**
	 * @param list<mixed> $arguments
	 * @return array{list<mixed>, list<mixed>} the resolved arguments, and the data arguments left over
	 * @throws InvalidBenchmarkException
	 */
	private function fill(ReflectionFunctionAbstract $function, array $arguments): array
	{
		$remaining = $arguments;
		$resolved = [];
		foreach ($function->getParameters() as $parameter) {
			$this->checkNotAService($function, $parameter);
			// a variadic parameter is the last one
			if ($parameter->isVariadic()) {
				$resolved = [...$resolved, ...$remaining];
				$remaining = [];
			} elseif ($remaining !== []) {
				$resolved[] = array_shift($remaining);
			} else {
				throw new InvalidBenchmarkException($this->name($function), sprintf(
					'the data set has %s, but parameter $%s needs one too.',
					$this->arguments(count($arguments)),
					$parameter->getName(),
				));
			}
		}

		return [$resolved, $remaining];
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	private function checkNotAService(ReflectionFunctionAbstract $function, ReflectionParameter $parameter): void
	{
		$type = $parameter->getType();
		if (!$type instanceof ReflectionNamedType || !in_array($type->getName(), self::SERVICES, true)) {
			return;
		}

		throw new InvalidBenchmarkException($this->name($function), sprintf(
			'parameter $%s is a %s. Take it in the constructor of the class, a method gets only the arguments of its data set.',
			$parameter->getName(),
			$this->shortName($type->getName()),
		));
	}

	private function shortName(string $className): string
	{
		return $className === Meter::class ? 'Meter' : 'ScratchDirectory';
	}

	private function name(ReflectionFunctionAbstract $function): string
	{
		if ($function instanceof ReflectionMethod) {
			return $function->getDeclaringClass()->getName() . '::' . $function->getName() . '()';
		}

		return '{closure}()';
	}

	private function arguments(int $count): string
	{
		return $count === 1 ? '1 argument' : $count . ' arguments';
	}

}
