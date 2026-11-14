<?php declare(strict_types = 1);

namespace Tally\Tests;

use Override;
use Tally\Runner\InstrumentHooks;

/** Records the calls instead of calling the tally_hooks extension, for tests. */
final class RecordingInstrumentHooks implements InstrumentHooks
{

	/** @var list<string> */
	public array $calls;

	public function __construct()
	{
		$this->calls = [];
	}

	#[Override]
	public function startInstrumentation(): void
	{
		$this->calls[] = 'instrument';
	}

	#[Override]
	public function toggleCollect(): void
	{
		$this->calls[] = 'toggle';
	}

	#[Override]
	public function dump(string $part): void
	{
		$this->calls[] = 'dump ' . $part;
	}

}
