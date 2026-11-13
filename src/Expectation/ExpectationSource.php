<?php declare(strict_types = 1);

namespace Tally\Expectation;

use Tally\Metrics;

/**
 * Where the expected metrics of one benchmark case are stored. Tally ships #[Expected]; a data
 * provider can attach its own source to a data set with DataSet::withExpectations(), such as one
 * that keeps the numbers in a comment of the file the data set benchmarks.
 *
 * Writing is split from reading the file: tally accept writes what rewrite() returns, and a run
 * shows it as a diff for cases without expectations, which a contributor can apply.
 *
 * @api
 */
interface ExpectationSource
{

	/** null for a case without expectations yet */
	public function read(): ?Metrics;

	/** The file that keeps the expectations */
	public function file(): string;

	/**
	 * The code of the file with these expectations replaced by the measured metrics, or added. When
	 * several cases keep their expectations in one file, the code has the others' already replaced.
	 *
	 * @throws ExpectationException
	 */
	public function rewrite(string $code, Metrics $measured): string;

	/**
	 * Where reports point to
	 *
	 * @throws ExpectationException
	 */
	public function location(): SourceLocation;

}
