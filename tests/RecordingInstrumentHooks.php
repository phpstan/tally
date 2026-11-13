<?php declare(strict_types = 1);

namespace Tally\Tests;

use Override;
use Tally\Runner\InstrumentHooks;

/** Records the calls instead of calling CodSpeed's library, for tests. */
final class RecordingInstrumentHooks implements InstrumentHooks
{

	/** @var list<string> */
	public array $calls;

	public function __construct()
	{
		$this->calls = [];
	}

	#[Override]
	public function startBenchmark(): void
	{
		$this->calls[] = 'start';
	}

	#[Override]
	public function stopBenchmark(): void
	{
		$this->calls[] = 'stop';
	}

	#[Override]
	public function toggleCollect(): void
	{
		$this->calls[] = 'toggle';
	}

	#[Override]
	public function setExecutedBenchmark(string $uri): void
	{
		$this->calls[] = 'executed ' . $uri;
	}

}
