<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Report;

use Tally\Attribute\RelativeTolerance;
use Tally\CaseId;
use Tally\Comparison\CaseComparison;
use Tally\Comparison\Comparator;
use Tally\Comparison\TolerancePolicy;
use Tally\Complexity\GrowthPoint;
use Tally\Expectation\SourceLocation;
use Tally\Measurement;
use Tally\Metrics;
use Tally\Report\Report;

/** Comparisons of every status, for the report tests */
final class Comparisons
{

	public const FILE = '/repo/benchmarks/ABench.php';

	public static function mixed(): Report
	{
		$expected = ['instructions' => 1_000_000, 'cycles' => 1_200_000, 'memory' => 1024 * 1024];

		return new Report([
			self::compare('App\ABench::benchBroken', 10, $expected, null, 'RuntimeException: It broke.' . "\n" . '#0 /repo/benchmarks/ABench.php(12)'),
			self::compare('App\ABench::benchFaster', 20, $expected, ['instructions' => 500_000, 'cycles' => 1_150_000, 'memory' => 1024 * 1024], null),
			self::compare('App\ABench::benchNew', 30, null, ['instructions' => 1_204, 'cycles' => 1_387, 'memory' => 0], null),
			self::compare('App\ABench::benchOk', 40, $expected, ['instructions' => 1_050_000, 'cycles' => 1_200_000, 'memory' => 1024 * 1024], null),
			// slower in instructions and lower in memory: listed once, as slower, with both
			self::compare('App\ABench::benchSlower@big', 50, $expected, ['instructions' => 1_500_000, 'cycles' => 1_250_000, 'memory' => 800 * 1024], null),
			self::compare('App\ABench::benchSlower@small', 51, $expected, ['instructions' => 1_200_000, 'cycles' => 1_200_000, 'memory' => 1024 * 1024], null),
		]);
	}

	/**
	 * @param array<string, int>|null $expected
	 * @param array<string, int>|null $measured null for a failure
	 */
	public static function compare(string $id, int $line, ?array $expected, ?array $measured, ?string $failure): CaseComparison
	{
		return self::compareWith(new RelativeTolerance(0.1), $id, $line, $expected, $measured, $failure);
	}

	/** A case compared by its growth from A::a@small, by 10 times, whose base counted 1000 instructions */
	public static function grown(string $id, int $instructions): CaseComparison
	{
		$caseId = CaseId::fromString($id);
		$base = CaseId::fromString('A::a@small');

		return (new Comparator())->compareGrowth(
			$caseId,
			new SourceLocation(self::FILE, 1),
			new GrowthPoint('O(n)', $base, 10.0),
			new RelativeTolerance(0.1),
			Measurement::measured($caseId, Metrics::fromArray(['instructions' => $instructions])),
			Measurement::measured($base, Metrics::fromArray(['instructions' => 1000])),
		);
	}

	/**
	 * @param array<string, int>|null $expected
	 * @param array<string, int>|null $measured null for a failure
	 */
	public static function compareWith(TolerancePolicy $tolerance, string $id, int $line, ?array $expected, ?array $measured, ?string $failure): CaseComparison
	{
		$caseId = CaseId::fromString($id);

		return (new Comparator())->compare(
			$caseId,
			new SourceLocation(self::FILE, $line),
			$expected === null ? null : Metrics::fromArray($expected),
			$tolerance,
			$measured === null ? Measurement::failed($caseId, (string) $failure) : Measurement::measured($caseId, Metrics::fromArray($measured)),
		);
	}

}
