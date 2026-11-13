<?php declare(strict_types = 1);

namespace Tally\Examples;

use Tally\Attribute\Complexity;
use Tally\Attribute\Constant;
use Tally\Attribute\DataProvider;
use Tally\Attribute\Exponential;
use Tally\Attribute\Linear;
use Tally\Attribute\Linearithmic;
use Tally\Attribute\Logarithmic;
use Tally\Attribute\Polynomial;
use Tally\Attribute\Quadratic;
use function array_fill_keys;
use function count;
use function intdiv;
use function mt_srand;
use function range;
use function shuffle;
use function sort;

/**
 * Instead of expected numbers, a complexity class: Tally compares each data set with the one of
 * the smallest size, and fails when the instructions grew more than the formula allows.
 */
final class ComplexityBench
{

	/**
	 * @return iterable<string, array{list<int>}>
	 */
	public static function lists(): iterable
	{
		foreach ([100, 1000, 10000] as $size) {
			$numbers = range(1, $size);
			mt_srand($size);
			shuffle($numbers);
			yield 'size-' . $size => [$numbers];
		}
	}

	/**
	 * Data providers are not counted, so building the set is not either.
	 *
	 * @return iterable<string, array{array<int, true>}>
	 */
	public static function sets(): iterable
	{
		foreach ([100, 1000, 10000] as $size) {
			yield 'size-' . $size => [array_fill_keys(range(1, $size), true)];
		}
	}

	/**
	 * @return iterable<string, array{list<int>}>
	 */
	public static function sortedLists(): iterable
	{
		foreach ([100, 1000, 10000] as $size) {
			yield 'size-' . $size => [range(1, $size)];
		}
	}

	/**
	 * @return iterable<string, array{list<int>}>
	 */
	public static function smallLists(): iterable
	{
		foreach ([10, 20, 40] as $size) {
			yield 'size-' . $size => [range(1, $size)];
		}
	}

	/**
	 * @return iterable<string, array{int}>
	 */
	public static function bits(): iterable
	{
		foreach ([4, 8, 12] as $bits) {
			yield 'bits-' . $bits => [$bits];
		}
	}

	/**
	 * @return iterable<string, array{int}>
	 */
	public static function primes(): iterable
	{
		yield 'prime-101' => [101];
		yield 'prime-10007' => [10007];
		yield 'prime-1000003' => [1000003];
	}

	/**
	 * @return iterable<string, array{array{customer: string, items: list<int>}}>
	 */
	public static function orders(): iterable
	{
		foreach ([10, 100, 1000] as $size) {
			yield 'items-' . $size => [['customer' => 'ACME', 'items' => range(1, $size)]];
		}
	}

	/**
	 * The size is an array, its count is n.
	 *
	 * @param array<int, true> $set
	 */
	#[DataProvider('sets')]
	#[Constant('set')]
	public function benchLookup(array $set): bool
	{
		return isset($set[42]);
	}

	/**
	 * @param list<int> $numbers sorted
	 */
	#[DataProvider('sortedLists')]
	#[Logarithmic('numbers')]
	public function benchBinarySearch(array $numbers): ?int
	{
		$low = 0;
		$high = count($numbers) - 1;
		while ($low <= $high) {
			$middle = intdiv($low + $high, 2);
			$number = $numbers[$middle] ?? 0;
			if ($number === 1) {
				return $middle;
			}
			if ($number < 1) {
				$low = $middle + 1;
			} else {
				$high = $middle - 1;
			}
		}

		return null;
	}

	/**
	 * A path picks the size from inside an argument.
	 *
	 * @param array{customer: string, items: list<int>} $order
	 */
	#[DataProvider('orders')]
	#[Linear('order.items')]
	public function benchTotal(array $order): int
	{
		$total = 0;
		foreach ($order['items'] as $price) {
			$total += $price;
		}

		return $total;
	}

	/**
	 * @param list<int> $numbers
	 */
	#[DataProvider('lists')]
	#[Linearithmic('numbers')]
	public function benchSort(array $numbers): void
	{
		sort($numbers);
	}

	/**
	 * @param list<int> $numbers
	 */
	#[DataProvider('smallLists')]
	#[Quadratic('numbers')]
	public function benchPairs(array $numbers): int
	{
		$pairs = 0;
		foreach ($numbers as $a) {
			foreach ($numbers as $b) {
				$pairs += $a < $b ? 1 : 0;
			}
		}

		return $pairs;
	}

	/**
	 * @param list<int> $numbers
	 */
	#[DataProvider('smallLists')]
	#[Polynomial('numbers', degree: 3)]
	public function benchTriples(array $numbers): int
	{
		$triples = 0;
		foreach ($numbers as $a) {
			foreach ($numbers as $b) {
				foreach ($numbers as $c) {
					$triples += $a + $b === $c ? 1 : 0;
				}
			}
		}

		return $triples;
	}

	/**
	 * A number is the size itself.
	 */
	#[DataProvider('bits')]
	#[Exponential('bits')]
	public function benchSubsets(int $bits): int
	{
		$withFirst = 0;
		for ($subset = 0; $subset < 1 << $bits; $subset++) {
			$withFirst += $subset & 1;
		}

		return $withFirst;
	}

	/**
	 * Any formula of n: trial division stops at the square root.
	 */
	#[DataProvider('primes')]
	#[Complexity(size: 'number', formula: 'sqrt(n)')]
	public function benchIsPrime(int $number): bool
	{
		for ($divisor = 2; $divisor * $divisor <= $number; $divisor++) {
			if ($number % $divisor === 0) {
				return false;
			}
		}

		return true;
	}

}
