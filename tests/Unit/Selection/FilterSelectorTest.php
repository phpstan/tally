<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Selection;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Selection\FilterSelector;
use function array_fill_keys;

final class FilterSelectorTest extends TestCase
{

	private const IDS = [
		'App\\LoopBench::benchSum@size-10',
		'App\\LoopBench::benchSum@size-100',
		'App\\StringBench::benchConcat',
		'App\\FilesBench::benchFile@dir/a.php',
	];

	/**
	 * @return iterable<string, array{string, list<string>}>
	 */
	public static function dataSelect(): iterable
	{
		yield 'substring, case-insensitive' => ['loop', ['App\\LoopBench::benchSum@size-10', 'App\\LoopBench::benchSum@size-100']];
		yield 'regular expression' => ['/@size-10$/', ['App\\LoopBench::benchSum@size-10']];
		yield 'backslashes are literal in a substring' => ['App\\String', ['App\\StringBench::benchConcat']];
		yield 'nothing' => ['Missing', []];
		yield 'a slash alone is a substring' => ['/', ['App\\FilesBench::benchFile@dir/a.php']];
		yield 'a leading slash alone is a substring' => ['/a.php', ['App\\FilesBench::benchFile@dir/a.php']];
		yield 'a trailing slash alone is a substring' => ['dir/', ['App\\FilesBench::benchFile@dir/a.php']];
		yield 'an empty regular expression matches everything' => ['//', self::IDS];
	}

	/**
	 * @param list<string> $expected
	 */
	#[DataProvider('dataSelect')]
	public function testSelect(string $filter, array $expected): void
	{
		$cases = Cases::withCosts(array_fill_keys(self::IDS, null));

		$this->assertSame($expected, Cases::ids((new FilterSelector($filter))->select($cases)));
	}

	public function testRejectsAnInvalidRegularExpression(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Filter /[/ is not a valid regular expression.');
		new FilterSelector('/[/');
	}

}
