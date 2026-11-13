<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Runner;

use RuntimeException;
use Tally\Attribute\After;
use Tally\Attribute\Before;
use Tally\Meter;
use Tally\ScratchDirectory;

final class RunnerBench
{

	private string $booted = 'not booted';

	public function __construct()
	{
		Journal::$entries[] = 'construct';
	}

	#[Before]
	public function boot(ScratchDirectory $scratch, string $name): void
	{
		$this->booted = 'booted ' . $name;
		Journal::$entries[] = 'boot ' . $name . ' in ' . $scratch->path();
	}

	/** Private, the runner calls hooks of any visibility */
	#[Before]
	private function also(): void
	{
		Journal::$entries[] = 'also';
	}

	public function benchName(string $name, Meter $meter): void
	{
		Journal::$entries[] = 'bench ' . $name . ', ' . $this->booted;
		$meter->exclude(static function (): void {
			Journal::$entries[] = 'excluded';
		});
	}

	public function benchAllocate(): void
	{
		Journal::$memory?->allocate(1000);
	}

	public function benchThrows(string $name): void
	{
		Journal::$entries[] = 'bench throws';
		throw new RuntimeException('The benchmark is broken.');
	}

	public function benchLeavesPaused(Meter $meter, string $name): void
	{
		$meter->pause();
	}

	public function leavePaused(Meter $meter): void
	{
		$meter->pause();
	}

	#[After]
	public function tearDown(string $name): void
	{
		Journal::$entries[] = 'tear down ' . $name;
	}

}
