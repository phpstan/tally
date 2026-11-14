<?php declare(strict_types = 1);

namespace Tally\Runner;

use Override;

/**
 * Lets CodSpeed's CPU simulation count the span: each span becomes a part of the callgrind
 * profile named after the case, which tally run reads when the process has finished.
 */
final class CodSpeedCounter implements Counter
{

	public function __construct(private InstrumentHooks $hooks)
	{
	}

	#[Override]
	public function start(): void
	{
		$this->hooks->startBenchmark();
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
		$this->hooks->stopBenchmark();
		$this->hooks->setExecutedBenchmark($part);
	}

}
