<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Cli;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Tests\InProcessTally;
use function implode;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use const PHP_BINARY;

final class ApplicationTest extends TestCase
{

	private const SUITE = __DIR__ . '/../../Fixtures/Suite';
	private const HELP = <<<'HELP'
		Tally counts the instructions, cycles, and memory of PHP benchmarks.

		Usage: tally <command> [options] [arguments]

		Commands:
		  list     Lists the benchmark cases, one ID per line
		  run      Counts the cases under CodSpeed and compares them with the expected numbers
		  compare  Compares the results of tally run --output with the expected numbers again
		  accept   Makes the results of tally run --output the expected numbers of the changed cases

		Run tally <command> --help for the options of a command.

		HELP;

	private const LIST_HELP = <<<'HELP'
		Lists the benchmark cases, one ID per line

		Usage: tally list [options] [<path>...]

		Options:
		  --config=<file>    Configuration file, tally.php in the working directory by default
		  --filter=<filter>  Only cases whose ID contains this text, or matches it as a regular expression like /@size-10$/
		  --shard=<shard>    Only the cases of one shard, like 2/4, balanced by expected instructions
		  --help             Show this help

		HELP;

	private const SUITE_NAMESPACE = 'Tally\\Tests\\Fixtures\\Suite\\';

	public function testList(): void
	{
		[$exitCode, $output] = InProcessTally::run(__DIR__, [], ['list', self::SUITE, '--filter=Loop']);

		$this->assertSame(0, $exitCode);
		$this->assertSame(
			self::SUITE_NAMESPACE . "Nested\\LoopBench::benchSum@size-10\n"
			. self::SUITE_NAMESPACE . "Nested\\LoopBench::benchSum@size-100\n"
			. self::SUITE_NAMESPACE . "Nested\\LoopBench::benchSum@size-1000\n",
			$output,
		);
	}

	public function testListShardWithASeparateValue(): void
	{
		// a.php has no expectations, so it costs as much as the most expensive case and comes first
		[$exitCode, $output] = InProcessTally::run(__DIR__, [], ['list', self::SUITE, '--shard', '1/8']);

		$this->assertSame(0, $exitCode);
		$this->assertSame(self::SUITE_NAMESPACE . "FilesBench::benchFile@a.php\n", $output);
	}

	public function testListFiltersBeforeSharding(): void
	{
		// size-100 and size-1000, which has no expectations, cost 1000 instructions each, size-10 costs 100
		[$exitCode, $output] = InProcessTally::run(__DIR__, [], ['list', self::SUITE, '--filter=Loop', '--shard=2/2']);

		$this->assertSame(0, $exitCode);
		$this->assertSame(self::SUITE_NAMESPACE . "Nested\\LoopBench::benchSum@size-1000\n", $output);
	}

	public function testListReadsPathsFromTheConfiguration(): void
	{
		[$exitCode, $output] = InProcessTally::run(__DIR__, [], ['list', '--config=' . __DIR__ . '/../../Fixtures/Configuration/tally.php', '--filter=String']);

		$this->assertSame(0, $exitCode);
		$this->assertSame(self::SUITE_NAMESPACE . "StringBench::benchConcat\n" . self::SUITE_NAMESPACE . "StringBench::benchSprintf\n", $output);
	}

	public function testListReadsTheConfigurationInTheWorkingDirectory(): void
	{
		[$exitCode, $output] = InProcessTally::run(__DIR__ . '/../../Fixtures/Configuration/cwd', [], ['list']);

		$this->assertSame(0, $exitCode);
		$this->assertSame(self::SUITE_NAMESPACE . "StringBench::benchConcat\n" . self::SUITE_NAMESPACE . "StringBench::benchSprintf\n", $output);
	}

	public function testPathsOverrideTheConfiguration(): void
	{
		[$exitCode, $output] = InProcessTally::run(__DIR__, [], ['list', '--config=' . __DIR__ . '/../../Fixtures/Configuration/tally.php', self::SUITE . '/StringBench.php']);

		$this->assertSame(0, $exitCode);
		$this->assertSame(self::SUITE_NAMESPACE . "StringBench::benchConcat\n" . self::SUITE_NAMESPACE . "StringBench::benchSprintf\n", $output);
	}

	/**
	 * @return iterable<string, array{list<string>, string}>
	 */
	public static function dataUsageError(): iterable
	{
		yield 'no paths' => [['list', '--config=' . __DIR__ . '/../../Fixtures/Configuration/empty.php'], 'No benchmarks to load. Pass their paths, or list them in tally.php.'];
		yield 'no paths without configuration' => [['list'], 'No benchmarks to load.'];
		yield 'missing configuration' => [['list', '--config=' . __DIR__ . '/../../Fixtures/Configuration/missing.php'], 'Configuration file ' . __DIR__ . '/../../Fixtures/Configuration/missing.php does not exist.'];
		yield 'configuration of a wrong type' => [['list', '--config=' . __DIR__ . '/../../Fixtures/Configuration/not-configuration.php'], 'Configuration file ' . __DIR__ . '/../../Fixtures/Configuration/not-configuration.php must return Tally\\Configuration.'];
		yield 'invalid filter' => [['list', self::SUITE, '--filter=/[/'], 'Filter /[/ is not a valid regular expression.'];
		yield 'invalid shard' => [['list', self::SUITE, '--shard=3/2'], 'Shard 3/2 does not exist, use 1/2 to 2/2.'];
		yield 'a filter that matches nothing' => [['list', self::SUITE, '--filter=Sort'], 'No case matches --filter=Sort.'];
	}

	/**
	 * @param list<string> $arguments
	 */
	#[DataProvider('dataUsageError')]
	public function testUsageError(array $arguments, string $message): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, [], $arguments);

		$this->assertSame(2, $exitCode);
		$this->assertSame('', $output);
		$this->assertStringContainsString($message, $errorOutput);
	}

	public function testInvalidBenchmark(): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, [], ['list', __DIR__ . '/../../Fixtures/Invalid']);

		$this->assertSame(2, $exitCode);
		$this->assertSame('', $output);
		$this->assertSame("Tally\\Tests\\Fixtures\\Invalid\\InvalidBench::benchMissingProvider(): data provider missing() does not exist.\n", $errorOutput);
	}

	public function testNoBenchmarks(): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, [], ['list', 'tests/Fixtures/Loading/NoBenchmarks', 'tests/Fixtures/Loading/Static/../NoBenchmarks']);

		$this->assertSame(2, $exitCode);
		$this->assertSame('', $output);
		$this->assertSame(
			"No benchmarks in tests/Fixtures/Loading/NoBenchmarks, tests/Fixtures/Loading/Static/../NoBenchmarks.\nBenchmarks are the public, non-static bench* methods of classes in *Bench.php files.\n",
			$errorOutput,
		);
	}

	public function testListAShardWithoutCases(): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, [], ['list', self::SUITE . '/StringBench.php', '--shard=3/3']);

		$this->assertSame(0, $exitCode, $errorOutput);
		$this->assertSame('', $output);
	}

	public function testUnknownOption(): void
	{
		[$exitCode, , $errorOutput] = InProcessTally::run(__DIR__, [], ['list', '--colour']);

		$this->assertSame(2, $exitCode);
		$this->assertSame("Unknown option --colour.\nRun tally list --help for its options.\n", $errorOutput);
	}

	public function testMissingOptionValue(): void
	{
		[$exitCode, , $errorOutput] = InProcessTally::run(__DIR__, [], ['list', '--shard']);

		$this->assertSame(2, $exitCode);
		$this->assertStringContainsString('Option --shard needs a value', $errorOutput);
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function dataUnknownCommand(): iterable
	{
		yield 'a mistyped command' => ['lst', "Unknown command lst, did you mean list?\nRun tally --help for the commands.\n"];
		yield 'not like any command' => ['deploy', "Unknown command deploy.\nRun tally --help for the commands.\n"];
		yield 'the hidden command is not suggested' => ['wroker', "Unknown command wroker.\nRun tally --help for the commands.\n"];
	}

	#[DataProvider('dataUnknownCommand')]
	public function testUnknownCommand(string $command, string $error): void
	{
		[$exitCode, $output, $errorOutput] = InProcessTally::run(__DIR__, [], [$command]);

		$this->assertSame(2, $exitCode);
		$this->assertSame('', $output);
		$this->assertSame($error, $errorOutput);
	}

	public function testHelpListsTheCommands(): void
	{
		[$exitCode, $output] = InProcessTally::run(__DIR__, [], []);

		$this->assertSame(0, $exitCode);
		$this->assertSame(self::HELP, $output);
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function dataCommandHelp(): iterable
	{
		$selection = <<<'OPTIONS'
			  --filter=<filter>     Only cases whose ID contains this text, or matches it as a regular expression like /@size-10$/
			  --shard=<shard>       Only the cases of one shard, like 2/4, balanced by expected instructions
			OPTIONS;

		yield 'list' => ['list', self::LIST_HELP];
		yield 'run' => ['run', <<<HELP
			Counts the cases under CodSpeed and compares them with the expected numbers

			Usage: tally run [options] [<path>...]

			Options:
			  --config=<file>       Configuration file, tally.php in the working directory by default
			{$selection}
			  --jobs=<n>            How many cases run at once, the number of CPUs by default
			  --output=<directory>  Keep the results there, for tally compare and tally accept
			  --help                Show this help

			HELP];
		yield 'compare' => ['compare', <<<'HELP'
			Compares the results of tally run --output with the expected numbers again

			Usage: tally compare [options] <results> [<path>...]

			Options:
			  --config=<file>    Configuration file, tally.php in the working directory by default
			  --filter=<filter>  Only cases whose ID contains this text, or matches it as a regular expression like /@size-10$/
			  --shard=<shard>    Only the cases of one shard, like 2/4, balanced by expected instructions
			  --table            Also print every case with its metrics and status
			  --help             Show this help

			HELP];
		yield 'accept' => ['accept', <<<'HELP'
			Makes the results of tally run --output the expected numbers of the changed cases

			Usage: tally accept [options] <results> [<path>...]

			Options:
			  --config=<file>    Configuration file, tally.php in the working directory by default
			  --filter=<filter>  Only cases whose ID contains this text, or matches it as a regular expression like /@size-10$/
			  --shard=<shard>    Only the cases of one shard, like 2/4, balanced by expected instructions
			  --help             Show this help

			HELP];
	}

	#[DataProvider('dataCommandHelp')]
	public function testCommandHelp(string $command, string $help): void
	{
		[$exitCode, $output] = InProcessTally::run(__DIR__, [], [$command, '--help']);

		$this->assertSame(0, $exitCode);
		$this->assertSame($help, $output);
	}

	public function testBinary(): void
	{
		$process = proc_open(
			[PHP_BINARY, __DIR__ . '/../../../bin/tally', 'list', self::SUITE, '--filter=Indexed'],
			[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes,
		);
		self::assertNotFalse($process);
		self::assertArrayHasKey(1, $pipes);
		self::assertArrayHasKey(2, $pipes);
		$output = stream_get_contents($pipes[1]);
		$errorOutput = stream_get_contents($pipes[2]);

		$this->assertSame(0, proc_close($process), (string) $errorOutput);
		$this->assertSame(implode("\n", [self::SUITE_NAMESPACE . 'Nested\\IndexedBench::benchIndexed@#0', self::SUITE_NAMESPACE . 'Nested\\IndexedBench::benchIndexed@#1']) . "\n", $output);
	}

}
