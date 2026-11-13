<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;

/**
 * Marks a method of a benchmark class to call before every case of the class's benchmarks,
 * outside of counting. It runs once per case, in each process that runs the case, on the same
 * object as the benchmark. It takes the case's data set arguments, from the first one, as many
 * as it declares, and a Meter or a ScratchDirectory by type. Several run in declaration order.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Before
{

}
