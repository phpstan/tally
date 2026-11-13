<?php declare(strict_types = 1);

namespace Tally;

/**
 * Turns counting off and on inside a benchmark method, for work that belongs to the setup but
 * cannot move to a before method. A benchmark gets it by declaring a parameter of this type.
 *
 * Paused code still runs under the simulation, only its cost is not collected, and the simulated
 * caches keep their contents, so counting after resume() does not start cold. Memory allocated
 * while paused does not count either.
 *
 * @api
 */
interface Meter
{

	public function pause(): void;

	public function resume(): void;

	/**
	 * Calls the callback with counting paused.
	 *
	 * @template T
	 * @param-immediately-invoked-callable $callback
	 * @param callable(): T $callback
	 * @return T
	 */
	public function exclude(callable $callback): mixed;

}
