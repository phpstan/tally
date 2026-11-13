<?php declare(strict_types = 1);

namespace Tally\Expression;

use function array_keys;
use function sort;

/** The functions expressions can call, by name */
final class FunctionLibrary
{

	/** @var array<string, MathFunction> */
	private array $functions;

	/**
	 * @param list<MathFunction> $functions
	 */
	public function __construct(array $functions)
	{
		$byName = [];
		foreach ($functions as $function) {
			$byName[$function->name()] = $function;
		}

		$this->functions = $byName;
	}

	public function find(string $name): ?MathFunction
	{
		return $this->functions[$name] ?? null;
	}

	/**
	 * @return list<string> sorted
	 */
	public function names(): array
	{
		$names = array_keys($this->functions);
		sort($names);

		return $names;
	}

}
