<?php declare(strict_types = 1);

namespace Tally;

use InvalidArgumentException;
use function is_int;
use function sprintf;

/**
 * Values of some of the metrics, used both for what is expected and for what was measured.
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

	public function with(Metric $metric, int $value): self
	{
		$values = $this->values;
		$values[$metric->value] = $value;

		return new self($values);
	}

	public function get(Metric $metric): ?int
	{
		return $this->values[$metric->value] ?? null;
	}

	/** The values of both, those of $other where both have one */
	public function merge(self $other): self
	{
		return new self([...$this->values, ...$other->values]);
	}

	public function isEmpty(): bool
	{
		return $this->values === [];
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
