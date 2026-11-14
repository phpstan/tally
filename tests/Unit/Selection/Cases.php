<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Selection;

use Tally\Attribute\RelativeTolerance;
use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Complexity\GrowthPoint;
use Tally\Metrics;
use Tally\Tests\InMemoryExpectations;
use function array_map;

final class Cases
{

	/**
	 * @param array<string, int|null> $costs expected instructions keyed by case ID
	 * @return list<BenchmarkCase>
	 */
	public static function withCosts(array $costs): array
	{
		return self::withBases($costs, []);
	}

	/**
	 * @param array<string, int|null> $costs expected instructions keyed by case ID
	 * @param array<string, string> $bases of the cases compared by their growth, keyed by case ID
	 * @return list<BenchmarkCase>
	 */
	public static function withBases(array $costs, array $bases): array
	{
		$cases = [];
		foreach ($costs as $id => $instructions) {
			$base = $bases[$id] ?? null;
			$cases[] = new BenchmarkCase(
				CaseId::fromString($id),
				'/benchmarks/FooBench.php',
				[],
				0,
				[],
				[],
				new InMemoryExpectations($instructions === null ? null : Metrics::fromArray(['instructions' => $instructions]), '/benchmarks/InMemoryBench.php'),
				new RelativeTolerance(0.1),
				$base === null ? null : new GrowthPoint('O(n)', CaseId::fromString($base), 1.0),
			);
		}

		return $cases;
	}

	/**
	 * @param list<BenchmarkCase> $cases
	 * @return list<string>
	 */
	public static function ids(array $cases): array
	{
		return array_map(static fn (BenchmarkCase $case): string => $case->id->toString(), $cases);
	}

}
