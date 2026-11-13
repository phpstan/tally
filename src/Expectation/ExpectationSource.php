<?php declare(strict_types = 1);

namespace Tally\Expectation;

use Tally\Metrics;

/**
 * Where the expected metrics of one benchmark case are stored. Tally ships #[Expected]; a data
 * provider can attach its own source to a data set with DataSet::withExpectations(), such as one
 * that keeps the numbers in a comment of the file the data set benchmarks.
 */
interface ExpectationSource
{

	/** null for a case without expectations yet */
	public function read(): ?Metrics;

	/** Replaces the expectations, used by tally accept */
	public function write(Metrics $measured): void;

	/** Where reports point to */
	public function location(): SourceLocation;

}
