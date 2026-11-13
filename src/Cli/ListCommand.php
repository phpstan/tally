<?php declare(strict_types = 1);

namespace Tally\Cli;

/**
 * Prints the case IDs, one per line. Lists all cases in natural order, and the cases of a shard
 * from the most expensive one.
 */
final class ListCommand implements Command
{

	public function name(): string
	{
		return 'list';
	}

	public function description(): string
	{
		return 'Lists the benchmark cases, one ID per line';
	}

	public function argumentsUsage(): string
	{
		return '[<path>...]';
	}

	public function options(): array
	{
		return SelectedCases::options();
	}

	public function execute(Input $input, Output $output): int
	{
		foreach (SelectedCases::load($input) as $case) {
			$output->write($case->id->toString() . "\n");
		}

		return Application::EXIT_SUCCESS;
	}

}
