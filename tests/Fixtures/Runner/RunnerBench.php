<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Runner;

use RuntimeException;
use Tally\Attribute\After;
use Tally\Attribute\Before;
use Tally\Meter;
use Tally\ScratchDirectory;

final class RunnerBench
{

	private string $booted;

	public function __construct(private ScratchDirectory $scratch, private Meter $meter)
	{
		$this->booted = 'not booted';
		Journal::$entries[] = 'construct';
	}

	#[Before]
	public function boot(string $name): void
	{
		$this->booted = 'booted ' . $name;
		Journal::$entries[] = 'boot ' . $name . ' in ' . $this->scratch->path();
		$this->meter->exclude(static function (): void {
			Journal::$entries[] = 'excluded in a hook';
		});
	}

	/** Private, the runner calls hooks of any visibility */
	#[Before]
	private function also(): void
	{
		Journal::$entries[] = 'also';
	}

	public function benchName(string $name): void
	{
		Journal::$entries[] = 'bench ' . $name . ', ' . $this->booted;
		$this->meter->exclude(static function (): void {
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

	public function benchLeavesPaused(string $name): void
	{
		$this->meter->pause();
	}

	public function leavePaused(): void
	{
		$this->meter->pause();
	}

	public function benchThrowsPaused(string $name): void
	{
		$this->meter->pause();
		throw new RuntimeException('Thrown while paused.');
	}

	#[After]
	public function tearDown(string $name): void
	{
		Journal::$entries[] = 'tear down ' . $name;
	}

}
