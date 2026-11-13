<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Selection;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Selection\ShardSelector;
use function array_merge;
use function count;
use function range;
use function sort;

final class ShardSelectorTest extends TestCase
{

	/**
	 * @return iterable<string, array{array<string, int|null>, list<list<string>>}>
	 */
	public static function dataShards(): iterable
	{
		// Handing these out in turn would put 10, 7, 5 and 2 (24) into one shard and 8, 6 and 4 (18)
		// into the other. Always picking the cheaper shard ends up with 22 and 20.
		yield 'balances the expected instructions' => [
			['A::c' => 7, 'A::a' => 10, 'A::e' => 5, 'A::b' => 8, 'A::d' => 6, 'A::f' => 4, 'A::g' => 2],
			[['A::a', 'A::d', 'A::f', 'A::g'], ['A::b', 'A::c', 'A::e']],
		];
		yield 'breaks ties by case order' => [
			['A::d' => 1, 'A::c' => 1, 'A::b' => 1, 'A::a' => 1],
			[['A::a', 'A::d'], ['A::b'], ['A::c']],
		];
		// with a cost of 0 or 1, the new case would join the shard of a
		yield 'a case without expectations costs as much as the most expensive one' => [
			['A::a' => 100, 'A::b' => 60, 'A::c' => 50, 'A::new' => null],
			[['A::a', 'A::b'], ['A::new', 'A::c']],
		];
		yield 'a case without expectations ties with the most expensive one' => [
			['A::a' => 1, 'A::b' => null, 'A::c' => 1],
			[['A::a', 'A::c'], ['A::b']],
		];
		yield 'cases without expectations spread over the shards' => [
			['A::a' => null, 'A::b' => null, 'A::c' => null],
			[['A::a', 'A::c'], ['A::b']],
		];
		yield 'one shard has every case, from the most expensive one' => [
			['A::a' => 1, 'A::b' => 2],
			[['A::b', 'A::a']],
		];
		yield 'more shards than cases' => [
			['A::a' => 3, 'A::b' => 1],
			[['A::a'], ['A::b'], [], []],
		];
	}

	/**
	 * @param array<string, int|null> $costs
	 * @param list<list<string>> $expected
	 */
	#[DataProvider('dataShards')]
	public function testShards(array $costs, array $expected): void
	{
		$cases = Cases::withCosts($costs);
		$count = count($expected);

		$shards = [];
		foreach (range(1, $count) as $shard) {
			$shards[] = Cases::ids(ShardSelector::fromString($shard . '/' . $count)->select($cases));
		}

		$this->assertSame($expected, $shards);
	}

	public function testCasesComparedByTheirGrowthStayWithTheirBase(): void
	{
		// the cases of A::sort cost 9 together, so A::x and A::y (9) go into the other shard, then A::find (3) into the first
		$cases = Cases::withBases(
			['A::sort@small' => 1, 'A::sort@large' => 8, 'A::x' => 5, 'A::y' => 4, 'A::find@small' => 2, 'A::find@large' => 1],
			['A::sort@small' => 'A::sort@small', 'A::sort@large' => 'A::sort@small', 'A::find@small' => 'A::find@small', 'A::find@large' => 'A::find@small'],
		);

		$this->assertSame(
			[['A::sort@small', 'A::sort@large', 'A::find@small', 'A::find@large'], ['A::x', 'A::y']],
			[Cases::ids((new ShardSelector(1, 2))->select($cases)), Cases::ids((new ShardSelector(2, 2))->select($cases))],
		);
	}

	public function testUnitsOfEqualCostsAreOrderedByTheirFirstCase(): void
	{
		$cases = Cases::withBases(
			['B::m@small' => 1, 'B::m@large' => 1, 'A::m@small' => 1, 'A::m@large' => 1],
			['B::m@small' => 'B::m@small', 'B::m@large' => 'B::m@small', 'A::m@small' => 'A::m@small', 'A::m@large' => 'A::m@small'],
		);

		$this->assertSame(['A::m@small', 'A::m@large', 'B::m@small', 'B::m@large'], Cases::ids((new ShardSelector(1, 1))->select($cases)));
	}

	public function testShardsAreDisjointAndCoverEveryCase(): void
	{
		$costs = [];
		for ($i = 0; $i < 23; $i++) {
			$costs['A::bench' . $i] = $i % 3 === 0 ? null : ($i * 7919) % 101;
		}
		$cases = Cases::withCosts($costs);

		$shards = [];
		foreach (range(1, 4) as $shard) {
			$shards[] = Cases::ids((new ShardSelector($shard, 4))->select($cases));
		}
		$all = array_merge(...$shards);
		sort($all);
		$expected = Cases::ids($cases);
		sort($expected);

		$this->assertSame($expected, $all);
	}

	/**
	 * @return iterable<array{string, string}>
	 */
	public static function dataInvalid(): iterable
	{
		yield ['0/2', 'Shard 0/2 does not exist, use 1/2 to 2/2.'];
		yield ['3/2', 'Shard 3/2 does not exist, use 1/2 to 2/2.'];
		yield ['1/0', 'Shard 1/0 does not exist, there must be at least one shard.'];
		yield ['1', 'Shard 1 is not like 2/4.'];
		yield ['a/b', 'Shard a/b is not like 2/4.'];
		yield ['x1/2', 'Shard x1/2 is not like 2/4.'];
		yield ['1/2x', 'Shard 1/2x is not like 2/4.'];
	}

	#[DataProvider('dataInvalid')]
	public function testFromStringRejects(string $shard, string $message): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		ShardSelector::fromString($shard);
	}

}
