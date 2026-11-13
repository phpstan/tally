<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Process;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Process\ProcessTable;

final class ProcessTableTest extends TestCase
{

	/**
	 * @return iterable<string, array{array<int, string|false>, array<int, int>}>
	 */
	public static function dataParents(): iterable
	{
		yield 'plain command' => [[118830 => '118830 (php) S 118827 118792 1 0 -1 4194560'], [118830 => 118827]];
		yield 'command with spaces and parentheses' => [[42 => '42 (a) (b c)) R 7 42 42 0'], [42 => 7]];
		yield 'ended before it was read' => [[42 => false, 43 => '43 (php) S 1'], [43 => 1]];
		yield 'no command' => [[42 => '42 S 7'], []];
		yield 'nothing after the command' => [[42 => '42 (php)'], []];
		yield 'only the state' => [[42 => '42 (php) S'], []];
		yield 'init' => [[1 => '1 (init) S 0 1 1 0'], [1 => 0]];
	}

	/**
	 * @param array<int, string|false> $stats
	 * @param array<int, int> $parents
	 */
	#[DataProvider('dataParents')]
	public function testParents(array $stats, array $parents): void
	{
		$this->assertSame($parents, (new ProcessTable())->parents($stats));
	}

	/**
	 * @return iterable<string, array{array<int, int>, int, list<int>}>
	 */
	public static function dataDescendants(): iterable
	{
		yield 'no children' => [[1 => 0, 10 => 1], 10, []];
		yield 'children before grandchildren' => [[30 => 20, 20 => 10, 21 => 10, 10 => 1, 40 => 1], 10, [20, 21, 30]];
		yield 'a sibling of the process is not a descendant' => [[10 => 1, 11 => 1, 12 => 11], 10, []];
		yield 'unknown process' => [[10 => 1], 99, []];
	}

	/**
	 * @param array<int, int> $parents
	 * @param list<int> $descendants
	 */
	#[DataProvider('dataDescendants')]
	public function testDescendants(array $parents, int $pid, array $descendants): void
	{
		$this->assertSame($descendants, (new ProcessTable())->descendants($parents, $pid));
	}

}
