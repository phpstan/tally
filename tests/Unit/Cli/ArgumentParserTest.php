<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Cli\ArgumentParser;
use Tally\Cli\Input;
use Tally\Cli\Option;
use Tally\Cli\UsageException;
use Tally\Text\ClosestName;

final class ArgumentParserTest extends TestCase
{

	/**
	 * @throws UsageException
	 */
	public function testOptionsAndArguments(): void
	{
		$input = self::parse(['a', '--filter=x=y', 'b', '--shard', '1/2', '--help', 'c']);

		$this->assertSame(['a', 'b', 'c'], $input->arguments());
		$this->assertSame('x=y', $input->option('filter'));
		$this->assertSame('1/2', $input->option('shard'));
		$this->assertTrue($input->flag('help'));
		$this->assertNull($input->option('help'));
		$this->assertFalse($input->flag('filter-missing'));
	}

	/**
	 * @throws UsageException
	 */
	public function testEverythingAfterDoubleDashIsPositional(): void
	{
		$input = self::parse(['a', '--', '--filter=x', '--', 'b']);

		$this->assertSame(['a', '--filter=x', '--', 'b'], $input->arguments());
		$this->assertNull($input->option('filter'));
	}

	/**
	 * @throws UsageException
	 */
	public function testEmptyValue(): void
	{
		$this->assertSame('', self::parse(['--filter=', 'a'])->option('filter'));
	}

	/**
	 * @throws UsageException
	 */
	public function testLaterValueWins(): void
	{
		$this->assertSame('b', self::parse(['--filter=a', '--filter', 'b'])->option('filter'));
	}

	/**
	 * @return iterable<string, array{list<string>, string}>
	 */
	public static function dataInvalid(): iterable
	{
		yield 'unknown' => [['--colour'], 'Unknown option --colour.'];
		yield 'unknown with a value' => [['--colour=red'], 'Unknown option --colour.'];
		yield 'mistyped' => [['--fitler=Sort'], 'Unknown option --fitler, did you mean --filter?'];
		yield 'flag with a value' => [['--help=yes'], 'Option --help does not take a value.'];
		yield 'missing value' => [['--filter'], 'Option --filter needs a value.'];
		yield 'option instead of a value' => [['--filter', '--help'], 'Option --filter needs a value.'];
	}

	/**
	 * @param list<string> $arguments
	 * @throws UsageException
	 */
	#[DataProvider('dataInvalid')]
	public function testInvalid(array $arguments, string $message): void
	{
		$this->expectException(UsageException::class);
		$this->expectExceptionMessage($message);
		self::parse($arguments);
	}

	/**
	 * @param list<string> $arguments
	 * @throws UsageException
	 */
	private static function parse(array $arguments): Input
	{
		return (new ArgumentParser(new ClosestName()))->parse($arguments, [
			new Option('filter', 'filter', ''),
			new Option('shard', 'shard', ''),
			new Option('help', null, ''),
		]);
	}

}
