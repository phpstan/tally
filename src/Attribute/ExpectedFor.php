<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use Tally\Metrics;
use Tally\Quantity\InvalidQuantityException;

/**
 * The metrics one data set of a benchmark method is expected to measure, such as
 * #[ExpectedFor('small', instructions: '18.20K', cycles: '21.04K', memory: '4.000KiB')], one per
 * data set. tally accept writes them.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ExpectedFor
{

	public function __construct(
		public readonly string $dataSet,
		public readonly string $instructions,
		public readonly string $cycles,
		public readonly string $memory
	)
	{
	}

	/**
	 * @throws InvalidQuantityException
	 */
	public function metrics(): Metrics
	{
		return Metrics::parse($this->instructions, $this->cycles, $this->memory);
	}

}
