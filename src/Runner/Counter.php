<?php declare(strict_types = 1);

namespace Tally\Runner;

/**
 * Counts the instructions and cycles of one span of the benchmark process, or lets an outside tool
 * like CodSpeed count them. Each span is a part of the profile, named after the case.
 */
interface Counter
{

	/** Called once for each case before its warm-up calls, so that they warm up what the counter's tool simulates */
	public function prepare(): void;

	public function start(): void;

	public function pause(): void;

	public function resume(): void;

	public function stop(string $part): void;

}
