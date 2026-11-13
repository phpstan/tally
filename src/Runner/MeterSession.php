<?php declare(strict_types = 1);

namespace Tally\Runner;

use Override;
use Tally\Meter;
use Tally\Metric;
use Tally\Metrics;
use function max;

/**
 * One counted span: drives the counter and measures the peak memory above the usage at its
 * start, leaving out what is allocated while paused. The counter starts after and stops before
 * the memory bookkeeping, so the bookkeeping is not counted.
 *
 * The only mutable object in Tally besides the fakes in the tests: a meter is paused and resumed.
 * A session counts one span and is then thrown away.
 */
final class MeterSession implements Meter
{

	/** The usage at the start plus what was allocated while paused, null before the start */
	private ?int $baseUsage;

	/** null while counting */
	private ?int $usageAtPause;

	private bool $stopped;

	/** @var list<int> the peak memory above the base usage of each counted span before the last pause */
	private array $peaksAboveBase;

	public function __construct(private Counter $counter, private Memory $memory)
	{
		$this->baseUsage = null;
		$this->usageAtPause = null;
		$this->stopped = false;
		$this->peaksAboveBase = [];
	}

	public function start(): void
	{
		if ($this->baseUsage !== null) {
			throw new MeterStateException('The meter has already started.');
		}

		$this->memory->resetPeak();
		$this->baseUsage = $this->memory->usage();
		$this->counter->start();
	}

	#[Override]
	public function pause(): void
	{
		$baseUsage = $this->counted('pause');
		if ($this->usageAtPause !== null) {
			throw new MeterStateException('Meter::pause() was called while the meter is paused.');
		}

		$this->counter->pause();
		$this->peaksAboveBase[] = $this->memory->peak() - $baseUsage;
		$this->usageAtPause = $this->memory->usage();
	}

	#[Override]
	public function resume(): void
	{
		$baseUsage = $this->counted('resume');
		if ($this->usageAtPause === null) {
			throw new MeterStateException('Meter::resume() was called while the meter is not paused.');
		}

		$this->baseUsage = $baseUsage + $this->memory->usage() - $this->usageAtPause;
		$this->usageAtPause = null;
		$this->memory->resetPeak();
		$this->counter->resume();
	}

	/**
	 * @template T
	 * @param-immediately-invoked-callable $callback
	 * @param callable(): T $callback
	 * @return T
	 */
	#[Override]
	public function exclude(callable $callback): mixed
	{
		$this->pause();
		try {
			return $callback();
		} finally {
			$this->resume();
		}
	}

	/**
	 * @return Metrics the memory; the counter's tool reports the instructions and cycles
	 */
	public function stop(string $part): Metrics
	{
		if ($this->baseUsage === null) {
			throw new MeterStateException('The meter was stopped before it started.');
		}
		if ($this->usageAtPause !== null) {
			throw new MeterStateException('The benchmark returned while the meter is paused, call Meter::resume() first.');
		}

		$this->counter->stop($part);
		$this->stopped = true;
		// the peak was reset at the start, so the first span's is never below zero
		$peaks = [...$this->peaksAboveBase, $this->memory->peak() - $this->baseUsage];

		return Metrics::none()->with(Metric::Memory, max($peaks));
	}

	/**
	 * @return int the base usage
	 */
	private function counted(string $method): int
	{
		if ($this->baseUsage === null || $this->stopped) {
			throw new MeterStateException('Meter::' . $method . '() was called while the benchmark is not counted.');
		}

		return $this->baseUsage;
	}

}
