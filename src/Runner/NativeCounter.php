<?php declare(strict_types = 1);

namespace Tally\Runner;

use Override;

/** Counts nothing: a run without CodSpeed measures only memory. */
final class NativeCounter implements Counter
{

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
	}

}
