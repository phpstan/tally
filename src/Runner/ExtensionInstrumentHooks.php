<?php declare(strict_types = 1);

namespace Tally\Runner;

use Override;
use function in_array;
use function Tally\Hooks\dump;
use function Tally\Hooks\instrumented;
use function Tally\Hooks\start;
use function Tally\Hooks\stop;
use function Tally\Hooks\toggle_collect;

/**
 * Calls Tally's tally_hooks extension (ext/tally_hooks), which makes Callgrind's client requests
 * like CodSpeed's instrument-hooks library does. tally run loads it into the counted worker.
 */
final class ExtensionInstrumentHooks implements InstrumentHooks
{

	public const EXTENSION = 'tally_hooks';

	private function __construct()
	{
	}

	/**
	 * @param list<string> $extensions the loaded PHP extensions
	 * @throws CodSpeedException
	 */
	public static function load(array $extensions): self
	{
		if (!in_array(self::EXTENSION, $extensions, true)) {
			throw new CodSpeedException("PHP did not load Tally's tally_hooks extension. Its warning is in the output of the process.");
		}
		if (!instrumented()) {
			throw new CodSpeedException("The benchmark does not run under CodSpeed's CPU simulation.");
		}

		return new self();
	}

	#[Override]
	public function startBenchmark(): void
	{
		start();
	}

	#[Override]
	public function stopBenchmark(): void
	{
		stop();
	}

	#[Override]
	public function toggleCollect(): void
	{
		toggle_collect();
	}

	#[Override]
	public function setExecutedBenchmark(string $uri): void
	{
		dump($uri);
	}

}
