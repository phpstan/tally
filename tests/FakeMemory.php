<?php declare(strict_types = 1);

namespace Tally\Tests;

use Override;
use Tally\Runner\Memory;
use function max;

/** Memory usage that changes only when a test allocates or frees, like PHP's allocator would report it. */
final class FakeMemory implements Memory
{

	private int $peak;

	public function __construct(private int $usage)
	{
		$this->peak = $usage;
	}

	public function allocate(int $bytes): void
	{
		$this->usage += $bytes;
		$this->peak = max($this->peak, $this->usage);
	}

	public function free(int $bytes): void
	{
		$this->usage -= $bytes;
	}

	#[Override]
	public function usage(): int
	{
		return $this->usage;
	}

	#[Override]
	public function peak(): int
	{
		return $this->peak;
	}

	#[Override]
	public function resetPeak(): void
	{
		$this->peak = $this->usage;
	}

}
