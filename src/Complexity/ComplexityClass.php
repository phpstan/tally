<?php declare(strict_types = 1);

namespace Tally\Complexity;

/**
 * How the instructions of a benchmark may grow with the size of its data sets, such as O(n log n).
 * Tally finds it among the attributes of a benchmark method by this interface: #[Complexity]
 * takes any formula, and the presets like #[Linear] give one.
 *
 * @api
 */
interface ComplexityClass
{

	/** The expression that picks the size from the arguments of a data set, such as numbers or input.rows */
	public function size(): string;

	/** The formula of n, the size, such as n * log(n) */
	public function formula(): string;

	/** For the report, such as O(n log n) */
	public function describe(): string;

}
