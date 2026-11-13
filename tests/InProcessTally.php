<?php declare(strict_types = 1);

namespace Tally\Tests;

use PHPUnit\Framework\Assert;
use Tally\Cli\ApplicationFactory;
use function fopen;
use function rewind;
use function stream_get_contents;
use const PHP_BINARY;

/**
 * Runs Tally's application in the test process, so that coverage counts it. Workers that tally
 * run starts are separate processes of bin/tally.
 */
final class InProcessTally
{

	public const BINARY = __DIR__ . '/../bin/tally';

	/**
	 * @param array<string, string> $environment
	 * @param list<string> $arguments without the program
	 * @return array{int, string, string} the exit code, the output, and the error output
	 */
	public static function run(string $workingDirectory, array $environment, array $arguments): array
	{
		$output = fopen('php://memory', 'w+');
		$errorOutput = fopen('php://memory', 'w+');
		Assert::assertNotFalse($output);
		Assert::assertNotFalse($errorOutput);

		$exitCode = (new ApplicationFactory())
			->create($output, $errorOutput, $workingDirectory, $environment, false, PHP_BINARY, self::BINARY)
			->run(['tally', ...$arguments]);

		rewind($output);
		rewind($errorOutput);

		return [$exitCode, (string) stream_get_contents($output), (string) stream_get_contents($errorOutput)];
	}

}
