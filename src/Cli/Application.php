<?php declare(strict_types = 1);

namespace Tally\Cli;

use Tally\Discovery\InvalidBenchmarkException;
use function array_map;
use function array_slice;
use function max;
use function sprintf;
use function str_pad;
use function strlen;

final class Application
{

	public const EXIT_SUCCESS = 0;
	/** A benchmark failed, or is slower than expected */
	public const EXIT_FAILURE = 1;
	public const EXIT_ERROR = 2;

	/**
	 * @param non-empty-list<Command> $commands
	 * @param list<Command> $hiddenCommands left out of the help, Tally runs them itself
	 */
	public function __construct(private Output $output, private ArgumentParser $parser, private array $commands, private array $hiddenCommands)
	{
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

		$command = $this->command($name);
		if ($command === null) {
			$this->output->writeError(sprintf("Unknown command %s.\n\n%s", $name, $this->help()));

			return self::EXIT_ERROR;
		}

		$options = [...$command->options(), new Option('help', null, 'Show this help')];
		try {
			$input = $this->parser->parse(array_slice($argv, 2), $options);
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

	private function command(string $name): ?Command
	{
		foreach ([...$this->commands, ...$this->hiddenCommands] as $command) {
			if ($command->name() === $name) {
				return $command;
			}
		}

		return null;
	}

	private function help(): string
	{
		$width = max(array_map(static fn (Command $command): int => strlen($command->name()), $this->commands));

		$help = "Tally counts the instructions, cycles, and memory of PHP benchmarks.\n\n";
		$help .= "Usage: tally <command> [options] [arguments]\n\nCommands:\n";
		foreach ($this->commands as $command) {
			$help .= sprintf("  %s  %s\n", str_pad($command->name(), $width), $command->description());
		}

		return $help . "\nRun tally <command> --help for the options of a command.\n";
	}

	/**
	 * @param non-empty-list<Option> $options
	 */
	private function commandHelp(Command $command, array $options): string
	{
		$width = max(array_map(static fn (Option $option): int => strlen($option->usage()), $options));

		$help = sprintf("%s\n\nUsage: tally %s [options] %s\n\nOptions:\n", $command->description(), $command->name(), $command->argumentsUsage());
		foreach ($options as $option) {
			$help .= sprintf("  %s  %s\n", str_pad($option->usage(), $width), $option->description);
		}

		return $help;
	}

}
