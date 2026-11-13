<?php declare(strict_types = 1);

namespace Tally\Bench\Selection;

use Tally\Attribute\DataProvider;
use Tally\Attribute\RelativeTolerance;
use Tally\Attribute\Warmup;
use Tally\Bench\SuiteSource;
use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Discovery\CaseExpander;
use Tally\Expectation\AttributeExpectations;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Selection\ShardSelector;
use function sprintf;

/**
 * Every shard of a CI run, and every worker it starts, selects its cases from the whole suite.
 */
#[Warmup(1)]
final class ShardSelectorBench
{

	/**
	 * @return iterable<string, array{ShardSelector, list<BenchmarkCase>}>
	 */
	public static function suites(): iterable
	{
		foreach ([[100, 4], [2000, 8]] as [$cases, $shards]) {
			yield sprintf('cases-%d-shards-%d', $cases, $shards) => [new ShardSelector(1, $shards), self::cases($cases)];
		}
	}

	/**
	 * @param list<BenchmarkCase> $cases
	 */
	#[DataProvider('suites')]
	public function benchSelect(ShardSelector $selector, array $cases): void
	{
		$selector->select($cases);
	}

	/**
	 * @return list<BenchmarkCase> with expected instructions spread over three orders of magnitude, every tenth without expectations
	 */
	private static function cases(int $count): array
	{
		$source = new SuiteSource();
		$rewriter = new ExpectedAttributeRewriter();
		$class = $source->className($count, 0);
		$cases = [];
		for ($i = 1; $i <= $count; $i++) {
			$method = sprintf('benchMethod%d', $i);
			$cases[] = new BenchmarkCase(
				new CaseId($class, $method, null),
				[],
				0,
				[],
				[],
				new AttributeExpectations($rewriter, SuiteSource::FILE, $class, $method, null, $i % 10 === 0 ? null : $source->measured($i * 7_919 % 1_000)),
				new RelativeTolerance(CaseExpander::DEFAULT_TOLERANCE),
			);
		}

		return $cases;
	}

}
