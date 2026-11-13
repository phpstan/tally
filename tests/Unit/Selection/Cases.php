<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Selection;

use Tally\Attribute\RelativeTolerance;
use Tally\BenchmarkCase;
use Tally\CaseId;
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
		$cases = [];
		foreach ($costs as $id => $instructions) {
			$cases[] = new BenchmarkCase(
				CaseId::fromString($id),
				[],
				0,
				[],
				[],
				new InMemoryExpectations($instructions === null ? null : Metrics::fromArray(['instructions' => $instructions])),
				new RelativeTolerance(0.1),
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
