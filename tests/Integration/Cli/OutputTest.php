<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Cli;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Cli\Output;
use function fopen;
use function rewind;
use function stream_get_contents;

final class OutputTest extends TestCase
{

	/**
	 * @return iterable<string, array{bool, string, string}>
	 */
	public static function dataError(): iterable
	{
		yield 'one line, without colors' => [false, 'It broke.', "It broke.\n"];
		yield 'one line, in bold red' => [true, 'It broke.', "\e[1;31mIt broke.\e[0m\n"];
		yield 'what to do after it stays plain' => [true, "It broke.\nRun tally --help.", "\e[1;31mIt broke.\e[0m\nRun tally --help.\n"];
		yield 'more lines, without colors' => [false, "It broke.\nRun tally --help.", "It broke.\nRun tally --help.\n"];
	}

	#[DataProvider('dataError')]
	public function testError(bool $errorColors, string $error, string $written): void
	{
		$stdout = fopen('php://memory', 'w+');
		$stderr = fopen('php://memory', 'w+');
		self::assertNotFalse($stdout);
		self::assertNotFalse($stderr);

		(new Output($stdout, $stderr, false, $errorColors))->error($error);

		rewind($stdout);
		rewind($stderr);
		$this->assertSame('', stream_get_contents($stdout));
		$this->assertSame($written, stream_get_contents($stderr));
	}

}
