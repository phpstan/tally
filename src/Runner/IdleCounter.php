<?php declare(strict_types = 1);

namespace Tally\Runner;

use Override;

/**
 * Counts nothing, for calls outside the counted span: before and after methods, warm-up calls, and the
 * native run that checks a case and fills its scratch directory before CodSpeed counts it.
 */
final class IdleCounter implements Counter
{

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
	}

}
