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

	private const NOT_STARTED = 0;
	private const COUNTING = 1;
	private const PAUSED = 2;
	private const STOPPED = 3;

	private int $state = self::NOT_STARTED;

	/** The usage at the start plus what was allocated while paused */
	private int $baseUsage = 0;

	private int $usageAtPause = 0;

	private int $peakAboveBase = 0;

	public function __construct(private Counter $counter, private Memory $memory)
	{
	}

	public function start(): void
	{
		if ($this->state !== self::NOT_STARTED) {
			throw new MeterStateException('The meter has already started.');
		}

		$this->memory->resetPeak();
		$this->baseUsage = $this->memory->usage();
		$this->state = self::COUNTING;
		$this->counter->start();
	}

	#[Override]
	public function pause(): void
	{
		$this->assertStarted('pause');
		if ($this->state === self::PAUSED) {
			throw new MeterStateException('Meter::pause() was called while the meter is paused.');
		}

		$this->counter->pause();
		$this->recordPeak();
		$this->usageAtPause = $this->memory->usage();
		$this->state = self::PAUSED;
	}

	#[Override]
	public function resume(): void
	{
		$this->assertStarted('resume');
		if ($this->state !== self::PAUSED) {
			throw new MeterStateException('Meter::resume() was called while the meter is not paused.');
		}

		$this->baseUsage += $this->memory->usage() - $this->usageAtPause;
		$this->memory->resetPeak();
		$this->state = self::COUNTING;
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
		if ($this->state === self::NOT_STARTED) {
			throw new MeterStateException('The meter was stopped before it started.');
		}
		if ($this->state === self::PAUSED) {
			throw new MeterStateException('The benchmark returned while the meter is paused, call Meter::resume() first.');
		}

		$this->counter->stop($part);
		$this->state = self::STOPPED;
		$this->recordPeak();

		return Metrics::none()->with(Metric::Memory, $this->peakAboveBase);
	}

	private function assertStarted(string $method): void
	{
		if ($this->state === self::COUNTING || $this->state === self::PAUSED) {
			return;
		}

		throw new MeterStateException('Meter::' . $method . '() was called while the benchmark is not counted.');
	}

	private function recordPeak(): void
	{
		$this->peakAboveBase = max($this->peakAboveBase, $this->memory->peak() - $this->baseUsage);
	}

}
