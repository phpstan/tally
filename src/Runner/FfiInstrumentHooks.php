<?php declare(strict_types = 1);

namespace Tally\Runner;

use FFI;
use FFI\CData;
use Override;
use function getmypid;
use function sprintf;

/**
 * Calls CodSpeed's instrument-hooks library (https://github.com/CodSpeedHQ/instrument-hooks)
 * over FFI. Its return codes are not checked: under the simulation the calls cannot fail.
 */
final class FfiInstrumentHooks implements InstrumentHooks
{

	private const DECLARATIONS = '
		typedef struct InstrumentHooks InstrumentHooks;
		InstrumentHooks *instrument_hooks_init(void);
		bool instrument_hooks_is_instrumented(InstrumentHooks *);
		uint8_t instrument_hooks_start_benchmark(InstrumentHooks *);
		uint8_t instrument_hooks_stop_benchmark(InstrumentHooks *);
		uint8_t instrument_hooks_set_executed_benchmark(InstrumentHooks *, int32_t pid, const char *uri);
		void callgrind_toggle_collect(void);
	';

	private function __construct(private FFI $ffi, private CData $instance)
	{
	}

	/**
	 * @throws CodSpeedException
	 */
	public static function load(string $library): self
	{
		try {
			$ffi = FFI::cdef(self::DECLARATIONS, $library);
		} catch (FFI\Exception $e) {
			throw new CodSpeedException(sprintf("Cannot load CodSpeed's instrument-hooks library %s: %s", $library, $e->getMessage()), previous: $e);
		}

		$instance = $ffi->instrument_hooks_init();
		if ($instance === null || !$ffi->instrument_hooks_is_instrumented($instance)) {
			throw new CodSpeedException("The benchmark does not run under CodSpeed's CPU simulation.");
		}

		return new self($ffi, $instance);
	}

	#[Override]
	public function startBenchmark(): void
	{
		$this->ffi->instrument_hooks_start_benchmark($this->instance);
	}

	#[Override]
	public function stopBenchmark(): void
	{
		$this->ffi->instrument_hooks_stop_benchmark($this->instance);
	}

	#[Override]
	public function toggleCollect(): void
	{
		$this->ffi->callgrind_toggle_collect();
	}

	#[Override]
	public function setExecutedBenchmark(string $uri): void
	{
		$this->ffi->instrument_hooks_set_executed_benchmark($this->instance, (int) getmypid(), $uri);
	}

}
