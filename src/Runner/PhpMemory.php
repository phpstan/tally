<?php declare(strict_types = 1);

namespace Tally\Runner;

use Override;
use function memory_get_peak_usage;
use function memory_get_usage;
use function memory_reset_peak_usage;

final class PhpMemory implements Memory
{

	#[Override]
	public function usage(): int
	{
		return memory_get_usage();
	}

	#[Override]
	public function peak(): int
	{
		return memory_get_peak_usage();
	}

	#[Override]
	public function resetPeak(): void
	{
		memory_reset_peak_usage();
	}

}
