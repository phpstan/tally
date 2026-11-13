<?php declare(strict_types = 1);

namespace Tally\Runner;

use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Meter;
use Tally\ScratchDirectory;
use function array_shift;
use function count;
use function sprintf;

/**
 * Fills the parameters of a benchmark, before, or after method: a parameter declared as Meter or
 * ScratchDirectory gets that service, the others get the arguments of the data set in order.
 * A parameter the data set has no argument for gets its default value.
 */
final class ArgumentResolver
{

	/**
	 * @param list<mixed> $arguments the data set's
	 * @return list<mixed>
	 * @throws InvalidBenchmarkException
	 */
	public function resolve(ReflectionFunctionAbstract $function, array $arguments, Meter $meter, ScratchDirectory $scratch): array
	{
		[$resolved, $remaining, $dataParameters] = $this->fill($function, $arguments, $meter, $scratch);
		if ($remaining !== []) {
			throw new InvalidBenchmarkException($this->name($function), sprintf(
				'the data set has %s, but the method takes %d.',
				$this->arguments(count($arguments)),
				$dataParameters,
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
	public function resolveHook(ReflectionFunctionAbstract $function, array $arguments, Meter $meter, ScratchDirectory $scratch): array
	{
		return $this->fill($function, $arguments, $meter, $scratch)[0];
	}

	/**
	 * @param list<mixed> $arguments
	 * @return array{list<mixed>, list<mixed>, int} the resolved arguments, the data arguments left over, and the number of data parameters
	 * @throws InvalidBenchmarkException
	 */
	private function fill(ReflectionFunctionAbstract $function, array $arguments, Meter $meter, ScratchDirectory $scratch): array
	{
		$remaining = $arguments;
		$resolved = [];
		$dataParameters = 0;
		foreach ($function->getParameters() as $parameter) {
			$service = $this->service($parameter, $meter, $scratch);
			if ($service !== null) {
				$resolved[] = $service;
				continue;
			}

			$dataParameters++;
			// a variadic parameter is the last one
			if ($parameter->isVariadic()) {
				$resolved = [...$resolved, ...$remaining];
				$remaining = [];
			} elseif ($remaining !== []) {
				$resolved[] = array_shift($remaining);
			} elseif ($parameter->isDefaultValueAvailable()) {
				$resolved[] = $parameter->getDefaultValue();
			} else {
				throw new InvalidBenchmarkException($this->name($function), sprintf(
					'the data set has %s, but parameter $%s needs one too.',
					$this->arguments(count($arguments)),
					$parameter->getName(),
				));
			}
		}

		return [$resolved, $remaining, $dataParameters];
	}

	private function service(ReflectionParameter $parameter, Meter $meter, ScratchDirectory $scratch): Meter|ScratchDirectory|null
	{
		$type = $parameter->getType();
		if (!$type instanceof ReflectionNamedType) {
			return null;
		}

		return match ($type->getName()) {
			Meter::class => $meter,
			ScratchDirectory::class => $scratch,
			default => null,
		};
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
