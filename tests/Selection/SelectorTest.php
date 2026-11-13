<?php declare(strict_types = 1);

namespace PHPStan\Tally\Selection;

use InvalidArgumentException;
use PHPStan\Tally\BenchmarkCase;
use PHPStan\Tally\CaseId;
use PHPStan\Tally\Expectation\InMemoryExpectations;
use PHPStan\Tally\Metrics;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function array_map;
use function array_merge;
use function sort;

class SelectorTest extends TestCase
{

	/**
	 * @return iterable<string, array{string, list<string>}>
	 */
	public static function dataFilter(): iterable
	{
		yield 'substring, case-insensitive' => ['loop', ['App\\LoopBench::benchSum@size-10', 'App\\LoopBench::benchSum@size-100']];
		yield 'regular expression' => ['/@size-10$/', ['App\\LoopBench::benchSum@size-10']];
		yield 'backslashes are literal in a substring' => ['App\\String', ['App\\StringBench::benchConcat']];
		yield 'nothing' => ['Missing', []];
	}

	/**
	 * @param list<string> $expected
	 */
	#[DataProvider('dataFilter')]
	public function testFilter(string $filter, array $expected): void
	{
		$cases = self::cases(['App\\LoopBench::benchSum@size-10' => null, 'App\\LoopBench::benchSum@size-100' => null, 'App\\StringBench::benchConcat' => null]);

		$this->assertSame($expected, self::ids((new FilterSelector($filter))->select($cases)));
	}

	public function testFilterRejectsAnInvalidRegularExpression(): void
	{
		$this->expectException(InvalidArgumentException::class);
		new FilterSelector('/[/');
	}

	public function testShardsBalanceTheExpectedInstructions(): void
	{
		// Handing these out in turn would put 10, 7, 5 and 2 (24) into one shard and 8, 6 and 4 (18)
		// into the other. Always picking the cheaper shard ends up with 22 and 20.
		$cases = self::cases(['A::c' => 7, 'A::a' => 10, 'A::e' => 5, 'A::b' => 8, 'A::d' => 6, 'A::f' => 4, 'A::g' => 2]);

		$this->assertSame(['A::a', 'A::d', 'A::f', 'A::g'], self::ids((new ShardSelector(1, 2))->select($cases)));
		$this->assertSame(['A::b', 'A::c', 'A::e'], self::ids((new ShardSelector(2, 2))->select($cases)));
	}

	public function testShardsBreakTiesByCaseOrder(): void
	{
		$cases = self::cases(['A::d' => 1, 'A::c' => 1, 'A::b' => 1, 'A::a' => 1]);

		$this->assertSame(['A::a', 'A::d'], self::ids((new ShardSelector(1, 3))->select($cases)));
		$this->assertSame(['A::b'], self::ids((new ShardSelector(2, 3))->select($cases)));
		$this->assertSame(['A::c'], self::ids((new ShardSelector(3, 3))->select($cases)));
	}

	public function testCaseWithoutExpectationsCostsAsMuchAsTheMostExpensiveOne(): void
	{
		// with a cost of 0 or 1, the new case would join the shard of a
		$cases = self::cases(['A::a' => 100, 'A::b' => 60, 'A::c' => 50, 'A::new' => null]);

		$this->assertSame(['A::a', 'A::b'], self::ids((new ShardSelector(1, 2))->select($cases)));
		$this->assertSame(['A::new', 'A::c'], self::ids((new ShardSelector(2, 2))->select($cases)));
	}

	public function testShardsAreDisjointAndCoverEveryCase(): void
	{
		$costs = [];
		for ($i = 0; $i < 23; $i++) {
			$costs['A::bench' . $i] = $i % 3 === 0 ? null : ($i * 7919) % 101;
		}
		$cases = self::cases($costs);

		$shards = [];
		for ($shard = 1; $shard <= 4; $shard++) {
			$shards[] = self::ids((new ShardSelector($shard, 4))->select($cases));
		}
		$all = array_merge(...$shards);
		sort($all);
		$expected = self::ids($cases);
		sort($expected);

		$this->assertSame($expected, $all);
	}

	public function testMoreShardsThanCases(): void
	{
		$cases = self::cases(['A::a' => 3, 'A::b' => 1]);

		$this->assertSame([], self::ids((new ShardSelector(3, 4))->select($cases)));
	}

	/**
	 * @return iterable<array{string}>
	 */
	public static function dataInvalidShard(): iterable
	{
		yield ['0/2'];
		yield ['3/2'];
		yield ['1'];
		yield ['a/b'];
		yield ['1/0'];
	}

	#[DataProvider('dataInvalidShard')]
	public function testFromStringRejects(string $shard): void
	{
		$this->expectException(InvalidArgumentException::class);
		ShardSelector::fromString($shard);
	}

	public function testFromString(): void
	{
		$cases = self::cases(['A::a' => 3, 'A::b' => 2, 'A::c' => 1]);

		$this->assertSame(['A::b'], self::ids(ShardSelector::fromString('2/3')->select($cases)));
	}

	/**
	 * @param array<string, int|null> $costs expected instructions keyed by case ID
	 * @return list<BenchmarkCase>
	 */
	private static function cases(array $costs): array
	{
		$cases = [];
		foreach ($costs as $id => $instructions) {
			$cases[] = new BenchmarkCase(
				CaseId::fromString($id),
				[],
				0,
				[],
				[],
				new InMemoryExpectations($instructions === null ? null : Metrics::fromArray(['instructions' => $instructions])),
			);
		}

		return $cases;
	}

	/**
	 * @param list<BenchmarkCase> $cases
	 * @return list<string>
	 */
	private static function ids(array $cases): array
	{
		return array_map(static fn (BenchmarkCase $case): string => $case->id->toString(), $cases);
	}

}
