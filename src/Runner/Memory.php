<?php declare(strict_types = 1);

namespace Tally\Runner;

/** PHP's memory usage, an interface so the arithmetic on it can be tested with exact numbers. */
interface Memory
{

	public function usage(): int;

	public function peak(): int;

	/** Sets the peak to the current usage */
	public function resetPeak(): void;

}
