<?php declare(strict_types = 1);

namespace Tally\Comparison;

use Tally\Metric;

/**
 * How far a measured metric may be from the expected one before the comparison reports it as
 * slower or faster. Tally finds a policy among the attributes of the benchmark method, then of
 * its class, by this interface, so a project can define its own policy as an attribute.
 *
 * @api
 */
interface TolerancePolicy
{

	/** The allowed relative difference, such as 0.1 for ±10 % */
	public function allowedChange(Metric $metric, int $expected): float;

	/** For the report, such as ±10 % of each metric */
	public function describe(): string;

}
