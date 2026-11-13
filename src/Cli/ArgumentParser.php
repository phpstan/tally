<?php declare(strict_types = 1);

namespace Tally\Cli;

use function array_keys;
use function array_shift;
use function explode;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function substr;

/**
 * Parses --name=value, --name value, --flag, and positional arguments. Everything after -- is positional.
 */
final class ArgumentParser
{

	public function __construct(private ClosestName $closestName)
	{
	}

	/**
	 * @param list<string> $arguments without the program and the command
	 * @param list<Option> $options
	 * @throws UsageException
	 */
	public function parse(array $arguments, array $options): Input
	{
		$byName = [];
		foreach ($options as $option) {
			$byName[$option->name] = $option;
		}

		$values = [];
		$positional = [];
		while ($arguments !== []) {
			$argument = array_shift($arguments);
			if ($argument === '--') {
				$positional = [...$positional, ...$arguments];
				break;
			}
			if (!str_starts_with($argument, '--')) {
				$positional[] = $argument;
				continue;
			}

			$name = substr($argument, 2);
			$value = null;
			if (str_contains($name, '=')) {
				[$name, $value] = explode('=', $name, 2);
			}
			$option = $byName[$name] ?? null;
			if ($option === null) {
				$closest = $this->closestName->find($name, array_keys($byName));
				throw new UsageException(sprintf('Unknown option --%s%s', $name, $closest === null ? '.' : sprintf(', did you mean --%s?', $closest)));
			}

			if ($option->valueName === null) {
				if ($value !== null) {
					throw new UsageException(sprintf('Option --%s does not take a value.', $name));
				}
				$values[$name] = true;
				continue;
			}

			if ($value === null) {
				$value = array_shift($arguments);
				if ($value === null || str_starts_with($value, '--')) {
					throw new UsageException(sprintf('Option --%s needs a value.', $name));
				}
			}
			$values[$name] = $value;
		}

		return new Input($values, $positional);
	}

}
