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

	/**
	 * @return iterable<string, array{string, list<string>}>
	 */
	public static function dataSelectWithBases(): iterable
	{
		yield 'a case compared by its growth brings its base' => ['benchSort@large', ['A::benchSort@small', 'A::benchSort@large']];
		yield 'its base does not bring the others' => ['benchSort@small', ['A::benchSort@small']];
		yield 'a base selected for several cases is there once' => ['/benchSort@(medium|large)$/', ['A::benchSort@small', 'A::benchSort@medium', 'A::benchSort@large']];
		yield 'only the base of the selected cases' => ['benchFind@large', ['A::benchFind@small', 'A::benchFind@large']];
		yield 'a case without growth brings nothing' => ['benchSum@large', ['A::benchSum@large']];
	}

	/**
	 * @param list<string> $expected
	 */
	#[DataProvider('dataSelectWithBases')]
	public function testSelectWithBases(string $filter, array $expected): void
	{
		$cases = Cases::withBases(
			array_fill_keys(['A::benchSort@small', 'A::benchSort@medium', 'A::benchSort@large', 'A::benchFind@small', 'A::benchFind@large', 'A::benchSum@small', 'A::benchSum@large'], null),
			[
				'A::benchSort@small' => 'A::benchSort@small',
				'A::benchSort@medium' => 'A::benchSort@small',
				'A::benchSort@large' => 'A::benchSort@small',
				'A::benchFind@small' => 'A::benchFind@small',
				'A::benchFind@large' => 'A::benchFind@small',
			],
		);

		$this->assertSame($expected, Cases::ids((new FilterSelector($filter))->select($cases)));
	}

	public function testRejectsAnInvalidRegularExpression(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Filter /[/ is not a valid regular expression.');
		new FilterSelector('/[/');
	}

}
