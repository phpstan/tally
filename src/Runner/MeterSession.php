<?php declare(strict_types = 1);

namespace Tally\Runner;

use Override;
use Tally\Meter;
use Tally\Metric;
use Tally\Metrics;
use function max;

/**
 * The meter of one case. Its benchmark object keeps it for the whole case, and every call Tally
 * makes on the object is a span of it: each hook and warm-up call with a counter that counts
 * nothing, the baseline and the benchmark with the real one. A span drives its counter and
 * measures the peak memory above the usage at its start, leaving out what is allocated while
 * paused. The counter starts after and stops before the memory bookkeeping, so the bookkeeping
 * is not counted.
 *
 * The only mutable object in Tally besides the fakes in the tests: a meter is paused and resumed.
 */
final class MeterSession implements Meter
{

	/** The counter of the current span, null between spans */
	private ?Counter $counter;

	/** The usage at the start plus what was allocated while paused */
	private int $baseUsage;

	/** null while counting */
	private ?int $usageAtPause;

	/** @var list<int> the peak memory above the base usage of each counted span before the last pause */
	private array $peaksAboveBase;

	public function __construct(private Memory $memory)
	{
		$this->counter = null;
		$this->baseUsage = 0;
		$this->usageAtPause = null;
		$this->peaksAboveBase = [];
	}

	/**
	 * Begins a span. A span a call left unfinished because it threw is dropped.
	 */
	public function start(Counter $counter): void
	{
		$this->usageAtPause = null;
		$this->peaksAboveBase = [];
		$this->memory->resetPeak();
		$this->baseUsage = $this->memory->usage();
		$this->counter = $counter;
		$counter->start();
	}

	#[Override]
	public function pause(): void
	{
		$counter = $this->counter('pause');
		if ($this->usageAtPause !== null) {
			throw new MeterStateException('Meter::pause() was called while the meter is paused.');
		}

		$counter->pause();
		$this->peaksAboveBase[] = $this->memory->peak() - $this->baseUsage;
		$this->usageAtPause = $this->memory->usage();
	}

	#[Override]
	public function resume(): void
	{
		$counter = $this->counter('resume');
		if ($this->usageAtPause === null) {
			throw new MeterStateException('Meter::resume() was called while the meter is not paused.');
		}

		$this->baseUsage += $this->memory->usage() - $this->usageAtPause;
		$this->usageAtPause = null;
		$this->memory->resetPeak();
		$counter->resume();
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
	 * Ends the span.
	 *
	 * @return Metrics the memory; the counter's tool reports the instructions and cycles
	 */
	public function stop(string $part): Metrics
	{
		if ($this->counter === null) {
			throw new MeterStateException('The meter was stopped before it started.');
		}
		if ($this->usageAtPause !== null) {
			throw new MeterStateException('The benchmark returned while the meter is paused, call Meter::resume() first.');
		}

		$this->counter->stop($part);
		$this->counter = null;
		// the peak was reset at the start, so the first span's is never below zero
		$peaks = [...$this->peaksAboveBase, $this->memory->peak() - $this->baseUsage];

		return Metrics::none()->with(Metric::Memory, max($peaks));
	}

	private function counter(string $method): Counter
	{
		if ($this->counter === null) {
			throw new MeterStateException('Meter::' . $method . '() was called while the benchmark is not counted.');
		}

		return $this->counter;
	}

}
