<?php declare(strict_types = 1);

namespace Tally\Cli;

use Tally\Discovery\InvalidBenchmarkException;
use Tally\Expectation\ExpectationException;

interface Command
{

	public function name(): string;

	public function description(): string;

	/** The positional arguments in the usage line, such as [<path>...] */
	public function argumentsUsage(): string;

	/**
	 * @return list<Option>
	 */
	public function options(): array;

	/**
	 * @return Application::EXIT_*
	 * @throws UsageException
	 * @throws InvalidBenchmarkException
	 * @throws ExpectationException
	 */
	public function execute(Input $input, Output $output): int;

}
