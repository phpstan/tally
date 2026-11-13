<?php declare(strict_types = 1);

namespace Tally\Examples;

use Tally\Attribute\Expected;
use Tally\Meter;
use function array_sum;
use function mb_strtoupper;
use function range;
use function str_repeat;

/**
 * A benchmark that declares a Meter parameter can turn counting off for work that is not
 * what it measures.
 */
final class MeterBench
{

	/**
	 * Tally ignores what a benchmark returns.
	 */
	#[Expected(instructions: '324.6K', cycles: '405.2K', memory: '384B')]
	public function benchExclude(Meter $meter): int
	{
		$numbers = $meter->exclude(static fn (): array => range(1, 10_000));

		return array_sum($numbers);
	}

	#[Expected(instructions: '2.588M', cycles: '2.721M', memory: '60.00KiB')]
	public function benchPauseAndResume(Meter $meter): string
	{
		$meter->pause();
		$text = str_repeat('tally ', 10_000);
		$meter->resume();

		return mb_strtoupper($text);
	}

}
