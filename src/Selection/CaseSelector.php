<?php declare(strict_types = 1);

namespace Tally\Selection;

use Tally\BenchmarkCase;

interface CaseSelector
{

	/**
	 * @param list<BenchmarkCase> $cases in the order of CaseId::compare()
	 * @return list<BenchmarkCase>
	 */
	public function select(array $cases): array;

}
