<?php declare(strict_types = 1);

namespace Tally;

use InvalidArgumentException;
use Tally\Quantity\InvalidQuantityException;
use function is_int;
use function max;
use function sprintf;

/**
 * Values of some of the metrics, used both for what is expected and for what was measured.
 *
 * @api
 */
final class Metrics
{

	/**
	 * @param array<value-of<Metric>, int> $values
	 */
	private function __construct(private array $values)
	{
	}

	public static function none(): self
	{
		return new self([]);
	}

	/**
	 * @param array<mixed> $values
	 * @throws InvalidArgumentException
	 */
	public static function fromArray(array $values): self
	{
		$metrics = self::none();
		foreach ($values as $name => $value) {
			$metric = Metric::tryFrom((string) $name);
			if ($metric === null || !is_int($value)) {
				throw new InvalidArgumentException(sprintf('Unknown metric %s, or its value is not an integer.', $name));
			}
			$metrics = $metrics->with($metric, $value);
		}

		return $metrics;
	}

	/**
	 * Every metric, from text such as 3.119B and 44.49MiB.
	 *
	 * @throws InvalidQuantityException
	 */
	public static function parse(string $instructions, string $cycles, string $memory): self
	{
		$values = [];
		foreach ([[Metric::Instructions, $instructions], [Metric::Cycles, $cycles], [Metric::Memory, $memory]] as [$metric, $text]) {
			try {
				$values[$metric->value] = $metric->format()->parse($text);
			} catch (InvalidQuantityException $e) {
				throw new InvalidQuantityException($metric->value . ' ' . $e->getMessage());
			}
		}

		return new self($values);
	}

	public function with(Metric $metric, int $value): self
	{
		$values = $this->values;
		$values[$metric->value] = $value;

		return new self($values);
	}

	/**
	 * Subtracts the metrics of a baseline, such as an empty call through the same path as the
	 * benchmark. Metrics the baseline does not have stay as they are.
	 */
	public function minus(self $baseline): self
	{
		$values = $this->values;
		foreach ($values as $name => $value) {
			$values[$name] = max(0, $value - ($baseline->values[$name] ?? 0));
		}

		return new self($values);
	}

	/** The other metrics replace these, the ones it does not have stay. */
	public function merge(self $other): self
	{
		return new self([...$this->values, ...$other->values]);
	}

	public function get(Metric $metric): ?int
	{
		return $this->values[$metric->value] ?? null;
	}

	/**
	 * @return array<value-of<Metric>, int> in the order of the metrics
	 */
	public function toArray(): array
	{
		$values = [];
		foreach (Metric::cases() as $metric) {
			if (!isset($this->values[$metric->value])) {
				continue;
			}

			$values[$metric->value] = $this->values[$metric->value];
		}

		return $values;
	}

}
