<?php declare(strict_types = 1);

namespace PHPStan\Tally\Cli;

use PHPStan\Tally\Discovery\InvalidBenchmarkException;
use function array_slice;
use function max;
use function sprintf;
use function str_pad;
use function strlen;

final class Application
{

	public const EXIT_SUCCESS = 0;
	public const EXIT_FAILURE = 1;
	public const EXIT_ERROR = 2;

	private Output $output;

	/** @var array<string, Command> */
	private array $commands = [];

	/**
	 * @param resource $stdout
	 * @param resource $stderr
	 * @param list<Command>|null $commands
	 */
	public function __construct($stdout, $stderr, ?array $commands = null)
	{
		$this->output = new Output($stdout, $stderr);
		foreach ($commands ?? [new ListCommand()] as $command) {
			$this->commands[$command->name()] = $command;
		}
	}

	/**
	 * @param list<string> $argv with the program name first
	 * @return self::EXIT_*
	 */
	public function run(array $argv): int
	{
		$name = $argv[1] ?? null;
		if ($name === null || $name === '--help' || $name === 'help') {
			$this->output->write($this->help());

			return self::EXIT_SUCCESS;
		}

		$command = $this->commands[$name] ?? null;
		if ($command === null) {
			$this->output->writeError(sprintf("Unknown command %s.\n\n%s", $name, $this->help()));

			return self::EXIT_ERROR;
		}

		$options = [...$command->options(), new Option('help', null, 'Show this help')];
		try {
			$input = (new ArgumentParser())->parse(array_slice($argv, 2), $options);
			if ($input->flag('help')) {
				$this->output->write($this->commandHelp($command, $options));

				return self::EXIT_SUCCESS;
			}

			return $command->execute($input, $this->output);
		} catch (UsageException $e) {
			$this->output->writeError($e->getMessage() . "\n\n" . $this->commandHelp($command, $options));

			return self::EXIT_ERROR;
		} catch (InvalidBenchmarkException $e) {
			$this->output->writeError($e->getMessage() . "\n");

			return self::EXIT_ERROR;
		}
	}

	private function help(): string
	{
		$width = 0;
		foreach ($this->commands as $name => $command) {
			$width = max($width, strlen($name));
		}

		$help = "Tally counts the instructions, cycles, and memory of PHP benchmarks.\n\n";
		$help .= "Usage: tally <command> [options] [arguments]\n\nCommands:\n";
		foreach ($this->commands as $name => $command) {
			$help .= sprintf("  %s  %s\n", str_pad($name, $width), $command->description());
		}

		return $help . "\nRun tally <command> --help for the options of a command.\n";
	}

	/**
	 * @param list<Option> $options
	 */
	private function commandHelp(Command $command, array $options): string
	{
		$width = 0;
		foreach ($options as $option) {
			$width = max($width, strlen($option->usage()));
		}

		$help = sprintf("%s\n\nUsage: tally %s [options] %s\n\nOptions:\n", $command->description(), $command->name(), $command->argumentsUsage());
		foreach ($options as $option) {
			$help .= sprintf("  %s  %s\n", str_pad($option->usage(), $width), $option->description);
		}

		return $help;
	}

}
