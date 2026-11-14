<?php declare(strict_types = 1);

namespace Tally\Runner;

/** The calls into Callgrind that CodSpeedCounter makes, through the tally_hooks extension. */
interface InstrumentHooks
{

	/** Zeroes the counts and starts counting */
	public function startBenchmark(): void;

	public function stopBenchmark(): void;

	/** Turns collecting the counts off or on, keeping the simulated caches */
	public function toggleCollect(): void;

	/** Writes the counts so far as a part of the profile with this name */
	public function setExecutedBenchmark(string $uri): void;

}
