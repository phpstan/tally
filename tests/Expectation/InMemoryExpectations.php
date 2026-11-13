<?php declare(strict_types = 1);

namespace Tally\Expectation;

use Tally\Metrics;

/** Keeps the expectations of a data set in memory, for tests. */
final class InMemoryExpectations implements ExpectationSource
{

	public function __construct(private ?Metrics $metrics = null)
	{
	}

	public function read(): ?Metrics
	{
		return $this->metrics;
	}

	public function write(Metrics $measured): void
	{
		$this->metrics = $measured;
	}

	public function location(): SourceLocation
	{
		return new SourceLocation(__FILE__, 1);
	}

}
