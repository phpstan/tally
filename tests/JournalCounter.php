<?php declare(strict_types = 1);

namespace Tally\Tests;

use Override;
use Tally\Runner\Counter;
use Tally\Tests\Fixtures\Runner\Journal;

/** Writes its calls to the journal of the fixture benchmarks, so a test sees which of their calls were counted. */
final class JournalCounter implements Counter
{

	#[Override]
	public function start(): void
	{
		Journal::$entries[] = 'counter start';
	}

	#[Override]
	public function pause(): void
	{
		Journal::$entries[] = 'counter pause';
	}

	#[Override]
	public function resume(): void
	{
		Journal::$entries[] = 'counter resume';
	}

	#[Override]
	public function stop(string $part): void
	{
		Journal::$entries[] = 'counter stop ' . $part;
	}

}
