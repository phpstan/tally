<?php declare(strict_types = 1);

namespace Tally\Runner;

/** The calls into Callgrind that CodSpeedCounter makes, through the tally_hooks extension. */
interface InstrumentHooks
{

	/** Starts simulating the process with empty caches; it collects counts only while collecting is on */
	public function startInstrumentation(): void;

	/** Stops simulating the process, which then runs at Valgrind's quickest */
	public function stopInstrumentation(): void;

	/** Turns collecting the counts on or off, keeping the simulated caches */
	public function toggleCollect(): void;

	/** Writes the counts collected since the last dump as a part of the profile with this name */
	public function dump(string $part): void;

}
