<?php declare(strict_types = 1);

namespace Tally\Examples;

use Tally\Attribute\DurationTolerance;
use Tally\Attribute\RelativeTolerance;
use function hash;
use function str_repeat;

/**
 * How much the measured numbers may differ from the expected ones before the run fails. Without a
 * policy, a benchmark gets ±10 %. A policy on the class applies to its methods without one.
 */
#[RelativeTolerance(0.02)]
final class ToleranceBench
{

	public function benchStrict(): void
	{
		hash('sha256', str_repeat('tally', 1_000));
	}

	/**
	 * Quick benchmarks change by a larger share when unrelated code changes, so they get more.
	 */
	#[DurationTolerance(
		tolerances: [0 => 0.5, 100 => 0.25, 500 => 0.2, 2000 => 0.1],
		instructionsPerMillisecond: 5_800_000,
		cyclesPerMillisecond: 7_400_000,
		memory: 0.1,
	)]
	public function benchByDuration(): void
	{
		hash('sha256', str_repeat('tally', 100_000));
	}

}
