<?php declare(strict_types = 1);

namespace Tally\Expectation;

use Tally\Metrics;

/**
 * Where the expected metrics of one benchmark case are stored. Tally ships #[Expected]; a data
 * provider can attach its own source to a data set with DataSet::withExpectations(), such as one
 * that keeps the numbers in a comment of the file the data set benchmarks.
 *
 * Writing is split from reading the file: tally accept writes the code with the edits of edits()
 * applied, and a run shows them as a diff for cases without expectations, which a contributor can
 * apply. Tally applies the edits of all cases that keep their expectations in one file at once,
 * so many cases in a long file cost one pass over it.
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
	 * The edits of the code of the file that replace these expectations with the measured metrics,
	 * or add them. The edits of the cases of one file must not overlap, except that equal edits,
	 * such as the same import, are made once. Edits that insert at the same offset are applied in
	 * the natural order of their text.
	 *
	 * @return list<CodeEdit>
	 * @throws ExpectationException
	 */
	public function edits(string $code, Metrics $measured): array;

	/**
	 * Where reports point to
	 *
	 * @throws ExpectationException
	 */
	public function location(): SourceLocation;

}
