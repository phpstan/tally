<?php declare(strict_types = 1);

namespace Tally\Bench\Expectation;

use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;
use Tally\Attribute\Warmup;
use Tally\Bench\SuiteSource;
use Tally\BenchmarkCase;
use Tally\Expectation\ExpectationChanges;
use Tally\Metrics;
use function sprintf;

/**
 * tally run plans the expectations of the new cases to print them as a patch, and tally accept
 * plans the changes of every file it writes. Each case of a file rewrites the code the previous
 * one left.
 */
#[Warmup(1)]
final class ExpectationChangesBench
{

	/**
	 * @return iterable<string, array{list<array{BenchmarkCase, Metrics}>, array<string, string>}>
	 */
	public static function newCases(): iterable
	{
		$source = new SuiteSource();
		foreach ([1, 100] as $size) {
			yield sprintf('cases-%d', 2 * $size) => [
				$source->measuredCases($size, $size),
				[SuiteSource::FILE => $source->code($size, $size, false)],
			];
		}
	}

	/**
	 * @param list<array{BenchmarkCase, Metrics}> $accepted
	 * @param array<string, string> $code
	 */
	#[DataProvider('newCases')]
	#[ExpectedFor('cases-2', instructions: '641.4K', cycles: '749.8K', memory: '35.08KiB')]
	#[ExpectedFor('cases-200', instructions: '1.895B', cycles: '2.071B', memory: '1.043MiB')]
	public function benchPlan(array $accepted, array $code): void
	{
		(new ExpectationChanges())->plan($accepted, $code);
	}

}
