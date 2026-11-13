<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use Tally\Metrics;
use Tally\Quantity\InvalidQuantityException;

/**
 * The metrics a benchmark method without a data provider is expected to measure, such as
 * #[Expected(instructions: '1.204K', cycles: '1.387K', memory: '0B')]. tally accept writes it.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Expected
{

	public function __construct(
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
