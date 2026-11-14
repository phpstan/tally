<?php declare(strict_types = 1);

namespace Tally\Examples;

use Tally\Attribute\DataProvider;
use Tally\Attribute\Expected;
use Tally\Attribute\ExpectedFor;
use function mt_srand;
use function range;
use function shuffle;
use function sort;

/**
 * The basics: a method whose name starts with bench is a benchmark. Expected numbers go in
 * #[Expected], or in #[ExpectedFor] for each data set of a data provider.
 */
final class SortBench
{

	#[Expected(instructions: '59.04K', cycles: '87.55K', memory: '10.59KiB')]
	public function benchSortHundred(): void
	{
		$numbers = range(100, 1, -1);
		sort($numbers);
	}

	/**
	 * @return iterable<string, array{list<int>}>
	 */
	public static function shuffled(): iterable
	{
		foreach ([10, 1000] as $size) {
			$numbers = range(1, $size);
			// the same order on every run, the counts must not change
			mt_srand($size);
			shuffle($numbers);
			yield 'size-' . $size => [$numbers];
		}
	}

	/**
	 * Data providers run before counting, so building the input is not counted.
	 *
	 * @param list<int> $numbers
	 */
	#[DataProvider('shuffled')]
	#[ExpectedFor('size-10', instructions: '4.563K', cycles: '16.21K', memory: '1.023KiB')]
	#[ExpectedFor('size-1000', instructions: '955.3K', cycles: '1.119M', memory: '59.88KiB')]
	public function benchSort(array $numbers): void
	{
		sort($numbers);
	}

}
