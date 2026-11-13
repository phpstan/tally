<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use Tally\Metric;
use Tally\Metrics;
use Tally\Quantity\InvalidQuantityException;

/**
 * The metrics a benchmark is expected to measure, such as #[Expected(instructions: '1.204K', cycles: '1.387K', memory: '0B')].
 * A method with a data provider has one per data set, named by dataSet. tally accept writes them.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Expected
{

	public function __construct(
		public readonly ?string $instructions = null,
		public readonly ?string $cycles = null,
		public readonly ?string $memory = null,
		public readonly ?string $dataSet = null
	)
	{
	}

	/**
	 * @throws InvalidQuantityException
	 */
	public function metrics(): Metrics
	{
		$metrics = Metrics::none();
		foreach ([[Metric::Instructions, $this->instructions], [Metric::Cycles, $this->cycles], [Metric::Memory, $this->memory]] as [$metric, $text]) {
			if ($text === null) {
				continue;
			}

			$metrics = $metrics->with($metric, $metric->format()->parse($text));
		}

		return $metrics;
	}

}
