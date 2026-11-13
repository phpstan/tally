<?php declare(strict_types = 1);

namespace Tally\Discovery;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Tally\Meter;
use Tally\ScratchDirectory;
use function sprintf;

/**
 * The arguments of the constructor of a benchmark class: its Meter and its ScratchDirectory,
 * by the types of the parameters, in any order. Data comes from data providers, so a
 * constructor takes nothing else.
 */
final class ConstructorArguments
{

	/**
	 * @param ReflectionClass<object> $class
	 * @throws InvalidBenchmarkException
	 */
	public function check(ReflectionClass $class): void
	{
		foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
			$this->isMeter($class, $parameter);
		}
	}

	/**
	 * @param ReflectionClass<object> $class
	 * @return list<Meter|ScratchDirectory>
	 * @throws InvalidBenchmarkException
	 */
	public function resolve(ReflectionClass $class, Meter $meter, ScratchDirectory $scratch): array
	{
		$arguments = [];
		foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
			$arguments[] = $this->isMeter($class, $parameter) ? $meter : $scratch;
		}

		return $arguments;
	}

	/**
	 * @param ReflectionClass<object> $class
	 * @return bool whether the parameter takes the Meter, else it takes the ScratchDirectory
	 * @throws InvalidBenchmarkException
	 */
	private function isMeter(ReflectionClass $class, ReflectionParameter $parameter): bool
	{
		$type = $parameter->getType();
		$name = $type instanceof ReflectionNamedType ? $type->getName() : null;
		if ($name !== Meter::class && $name !== ScratchDirectory::class) {
			throw new InvalidBenchmarkException($class->getName() . '::__construct()', sprintf(
				'parameter $%s is neither a Meter nor a ScratchDirectory. Tally creates the class with a Meter and a ScratchDirectory only, by the types of the parameters. Data comes from a data provider.',
				$parameter->getName(),
			));
		}

		return $name === Meter::class;
	}

}
