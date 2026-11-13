<?php declare(strict_types = 1);

namespace Tally\Cli;

use Override;

/**
 * Prints the case IDs, one per line. Lists all cases in natural order, and the cases of a shard
 * from the most expensive one.
 */
final class ListCommand implements Command
{

	public function __construct(private SelectedCases $selectedCases)
	{
	}

	#[Override]
	public function name(): string
	{
		return 'list';
	}

	#[Override]
	public function description(): string
	{
		return 'Lists the benchmark cases, one ID per line';
	}

	#[Override]
	public function argumentsUsage(): string
	{
		return '[<path>...]';
	}

	#[Override]
	public function options(): array
	{
		return [...$this->selectedCases->suiteOptions(), ...$this->selectedCases->selectionOptions()];
	}

	#[Override]
	public function execute(Input $input, Output $output): int
	{
		foreach ($this->selectedCases->load($input, $this->selectedCases->configuration($input), $input->arguments()) as $case) {
			$output->write($case->id->toString() . "\n");
		}

		return Application::EXIT_SUCCESS;
	}

}
