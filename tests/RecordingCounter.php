<?php declare(strict_types = 1);

namespace Tally\Tests;

use Override;
use Tally\Runner\Counter;

/** Records the calls instead of counting, for tests. */
final class RecordingCounter implements Counter
{

	/** @var list<string> */
	public array $calls;

	public function __construct()
	{
		$this->calls = [];
	}

	#[Override]
	public function prepare(): void
	{
		$this->calls[] = 'prepare';
	}

	#[Override]
	public function start(): void
	{
		$this->calls[] = 'start';
	}

	#[Override]
	public function pause(): void
	{
		$this->calls[] = 'pause';
	}

	#[Override]
	public function resume(): void
	{
		$this->calls[] = 'resume';
	}

	#[Override]
	public function stop(string $part): void
	{
		$this->calls[] = 'stop ' . $part;
	}

}
