<?php declare(strict_types = 1);

namespace Tally\Runner;

use Override;

/**
 * Lets CodSpeed's CPU simulation count the span: each span becomes a part of the callgrind
 * profile named after the case, which tally run reads when the process has finished.
 *
 * Callgrind empties its simulated caches when it starts the instrumentation, so the counter starts
 * it once, before the warm-up calls, and only turns collecting on and off for each span. The caches
 * stay warm from one span to the next, and the cycles of a small benchmark do not depend on which
 * cache lines the code between the spans happens to touch. It stops the instrumentation after the
 * last span, so the after methods and the end of the process run at Valgrind's quickest.
 */
final class CodSpeedCounter implements Counter
{

	public function __construct(private InstrumentHooks $hooks)
	{
	}

	#[Override]
	public function prepare(): void
	{
		$this->hooks->startInstrumentation();
	}

	#[Override]
	public function finish(): void
	{
		$this->hooks->stopInstrumentation();
	}

	#[Override]
	public function start(): void
	{
		$this->hooks->toggleCollect();
	}

	#[Override]
	public function pause(): void
	{
		$this->hooks->toggleCollect();
	}

	#[Override]
	public function resume(): void
	{
		$this->hooks->toggleCollect();
	}

	#[Override]
	public function stop(string $part): void
	{
		$this->hooks->toggleCollect();
		$this->hooks->dump($part);
	}

}
