<?php declare(strict_types = 1);

namespace Tally\Tests;

use Override;
use Tally\Expectation\ExpectationSource;
use Tally\Expectation\SourceLocation;
use Tally\Metrics;

/** Keeps the expectations of a data set in memory, for tests. */
final class InMemoryExpectations implements ExpectationSource
{

	public function __construct(private ?Metrics $metrics = null)
	{
	}

	#[Override]
	public function read(): ?Metrics
	{
		return $this->metrics;
	}

	#[Override]
	public function write(Metrics $measured): void
	{
		$this->metrics = $measured;
	}

	#[Override]
	public function location(): SourceLocation
	{
		return new SourceLocation(__FILE__, 1);
	}

}
