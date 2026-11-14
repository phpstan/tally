<?php declare(strict_types = 1);

namespace Tally\Tests;

use Override;
use Tally\Runner\Counter;

/** Allocates while each span stops, like the call path of the baseline and the benchmark does in PHP. */
final class AllocatingCounter implements Counter
{

	public function __construct(private FakeMemory $memory, private int $bytes)
	{
	}

	#[Override]
	public function prepare(): void
	{
	}

	#[Override]
	public function start(): void
	{
	}

	#[Override]
	public function pause(): void
	{
	}

	#[Override]
	public function resume(): void
	{
	}

	#[Override]
	public function stop(string $part): void
	{
		$this->memory->allocate($this->bytes);
	}

}
