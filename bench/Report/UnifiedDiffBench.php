<?php declare(strict_types = 1);

namespace Tally\Bench\Report;

use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;
use Tally\Attribute\Warmup;
use Tally\Bench\SuiteSource;
use Tally\Report\UnifiedDiff;
use function array_map;
use function implode;
use function range;
use function sprintf;

/**
 * tally run prints the expectations of new cases as a patch. The diff compares every line between
 * the first and the last change with every other, so its cost grows with the distance between them.
 */
#[Warmup(1)]
final class UnifiedDiffBench
{

	private const LINES = 1000;

	/**
	 * @return iterable<string, array{string, string}> the code before and after
	 */
	public static function changes(): iterable
	{
		$lines = array_map(static fn (int $i): string => sprintf("line %d\n", $i), range(1, self::LINES));
		$changed = $lines;
		$changed[self::LINES / 2] = "changed\n";
		yield 'middle-line-changed' => [implode('', $lines), implode('', $changed)];

		$changed = $lines;
		$changed[0] = "changed\n";
		$changed[self::LINES - 1] = "changed\n";
		yield 'first-and-last-line-changed' => [implode('', $lines), implode('', $changed)];

		$source = new SuiteSource();
		yield 'new-expectations-200' => [$source->code(100, 100, false), $source->code(100, 100, true)];
	}

	#[DataProvider('changes')]
	#[ExpectedFor('first-and-last-line-changed', instructions: '2.186B', cycles: '2.341B', memory: '39.59MiB')]
	#[ExpectedFor('middle-line-changed', instructions: '5.292M', cycles: '5.540M', memory: '375.6KiB')]
	#[ExpectedFor('new-expectations-200', instructions: '787.4M', cycles: '848.6M', memory: '20.46MiB')]
	public function benchRender(string $before, string $after): void
	{
		(new UnifiedDiff())->render('bench/SuiteBench.php', $before, $after);
	}

}
