<?php declare(strict_types = 1);

namespace PHPStan\Tally\Cli;

use PHPUnit\Framework\TestCase;
use function fopen;
use function implode;
use function proc_close;
use function proc_open;
use function rewind;
use function stream_get_contents;
use const PHP_BINARY;

class ApplicationTest extends TestCase
{

	private const SUITE = __DIR__ . '/../Discovery/data/suite';
	private const NAMESPACE = 'PHPStan\\Tally\\Discovery\\data\\suite\\';

	public function testList(): void
	{
		[$exitCode, $output] = self::runTally(['list', self::SUITE, '--filter=Loop']);

		$this->assertSame(0, $exitCode);
		$this->assertSame(
			self::NAMESPACE . "Nested\\LoopBench::benchSum@size-10\n"
			. self::NAMESPACE . "Nested\\LoopBench::benchSum@size-100\n"
			. self::NAMESPACE . "Nested\\LoopBench::benchSum@size-1000\n",
			$output,
		);
	}

	public function testListShardWithASeparateValue(): void
	{
		// a.php has no expectations, so it costs as much as the most expensive case and comes first
		[$exitCode, $output] = self::runTally(['list', self::SUITE, '--shard', '1/8']);

		$this->assertSame(0, $exitCode);
		$this->assertSame(self::NAMESPACE . "FilesBench::benchFile@a.php\n", $output);
	}

	public function testListReadsPathsFromTheConfiguration(): void
	{
		[$exitCode, $output] = self::runTally(['list', '--config=' . __DIR__ . '/data/tally.php', '--filter=String']);

		$this->assertSame(0, $exitCode);
		$this->assertSame(self::NAMESPACE . "StringBench::benchConcat\n" . self::NAMESPACE . "StringBench::benchSprintf\n", $output);
	}

	public function testInvalidBenchmark(): void
	{
		[$exitCode, $output, $errorOutput] = self::runTally(['list', __DIR__ . '/../Discovery/data/invalid']);

		$this->assertSame(2, $exitCode);
		$this->assertSame('', $output);
		$this->assertStringContainsString('InvalidBench::bench', $errorOutput);
	}

	public function testUnknownOption(): void
	{
		[$exitCode, , $errorOutput] = self::runTally(['list', '--colour']);

		$this->assertSame(2, $exitCode);
		$this->assertStringContainsString('Unknown option --colour', $errorOutput);
		$this->assertStringContainsString('Usage: tally list', $errorOutput);
	}

	public function testMissingOptionValue(): void
	{
		[$exitCode, , $errorOutput] = self::runTally(['list', '--shard']);

		$this->assertSame(2, $exitCode);
		$this->assertStringContainsString('Option --shard needs a value', $errorOutput);
	}

	public function testUnknownCommand(): void
	{
		[$exitCode, , $errorOutput] = self::runTally(['lst']);

		$this->assertSame(2, $exitCode);
		$this->assertStringContainsString('Unknown command lst', $errorOutput);
	}

	public function testHelpListsTheCommands(): void
	{
		[$exitCode, $output] = self::runTally([]);

		$this->assertSame(0, $exitCode);
		$this->assertStringContainsString('list', $output);
		$this->assertStringContainsString('Usage: tally <command>', $output);
	}

	public function testCommandHelp(): void
	{
		[$exitCode, $output] = self::runTally(['list', '--help']);

		$this->assertSame(0, $exitCode);
		$this->assertStringContainsString('--shard=<shard>', $output);
		$this->assertStringContainsString('--filter=<filter>', $output);
	}

	public function testBinary(): void
	{
		$process = proc_open(
			[PHP_BINARY, __DIR__ . '/../../bin/tally', 'list', self::SUITE, '--filter=Indexed'],
			[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes,
		);
		self::assertNotFalse($process);
		$output = stream_get_contents($pipes[1]);
		$errorOutput = stream_get_contents($pipes[2]);

		$this->assertSame(0, proc_close($process), (string) $errorOutput);
		$this->assertSame(implode("\n", [self::NAMESPACE . 'Nested\\IndexedBench::benchIndexed@#0', self::NAMESPACE . 'Nested\\IndexedBench::benchIndexed@#1']) . "\n", $output);
	}

	/**
	 * @param list<string> $arguments
	 * @return array{int, string, string}
	 */
	private static function runTally(array $arguments): array
	{
		$output = fopen('php://memory', 'w+');
		$errorOutput = fopen('php://memory', 'w+');
		self::assertNotFalse($output);
		self::assertNotFalse($errorOutput);

		$exitCode = (new Application($output, $errorOutput))->run(['tally', ...$arguments]);

		rewind($output);
		rewind($errorOutput);

		return [$exitCode, (string) stream_get_contents($output), (string) stream_get_contents($errorOutput)];
	}

}
