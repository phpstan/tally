<?php declare(strict_types = 1);

namespace Tally;

/**
 * What running one case gave: its metrics, or why it was not measured.
 */
final class Measurement
{

	private function __construct(
		public readonly CaseId $id,
		public readonly Metrics $metrics,
		public readonly ?string $failure
	)
	{
	}

	public static function measured(CaseId $id, Metrics $metrics): self
	{
		return new self($id, $metrics, null);
	}

	public static function failed(CaseId $id, string $failure): self
	{
		return new self($id, Metrics::none(), $failure);
	}

}
