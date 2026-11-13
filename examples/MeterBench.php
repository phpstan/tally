<?php declare(strict_types = 1);

namespace Tally\Examples;

use Tally\Attribute\Expected;
use Tally\Meter;
use function array_sum;
use function mb_strtoupper;
use function range;
use function str_repeat;

/**
 * A benchmark class that takes a Meter in its constructor can turn counting off for work that
 * is not what it measures.
 */
final class MeterBench
{

	public function __construct(private Meter $meter)
	{
	}

	/**
	 * Tally ignores what a benchmark returns.
	 */
	#[Expected(instructions: '324.6K', cycles: '411.1K', memory: '600B')]
	public function benchExclude(): int
	{
		$numbers = $this->meter->exclude(static fn (): array => range(1, 10_000));

		return array_sum($numbers);
	}

	#[Expected(instructions: '2.588M', cycles: '2.721M', memory: '60.00KiB')]
	public function benchPauseAndResume(): string
	{
		$this->meter->pause();
		$text = str_repeat('tally ', 10_000);
		$this->meter->resume();

		return mb_strtoupper($text);
	}

}
