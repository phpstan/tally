<?php declare(strict_types = 1);

namespace Tally\Cli;

use function count;
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
		$count = count($arguments);
		for ($i = 0; $i < $count; $i++) {
			$argument = $arguments[$i];
			if ($argument === '--') {
				for ($j = $i + 1; $j < $count; $j++) {
					$positional[] = $arguments[$j];
				}
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
				throw new UsageException(sprintf('Unknown option --%s.', $name));
			}

			if ($option->valueName === null) {
				if ($value !== null) {
					throw new UsageException(sprintf('Option --%s does not take a value.', $name));
				}
				$values[$name] = true;
				continue;
			}

			if ($value === null) {
				$next = $arguments[$i + 1] ?? null;
				if ($next === null || str_starts_with($next, '--')) {
					throw new UsageException(sprintf('Option --%s needs a value.', $name));
				}
				$value = $next;
				$i++;
			}
			$values[$name] = $value;
		}

		return new Input($values, $positional);
	}

}
