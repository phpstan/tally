<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;

/**
 * A public static method of the benchmark class that returns the data sets to call the benchmark
 * method with: arrays of arguments keyed by the data set's name, or DataSet objects.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class DataProvider
{

	public function __construct(public readonly string $method)
	{
	}

}
