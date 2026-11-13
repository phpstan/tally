<?php declare(strict_types = 1);

namespace Tally\Discovery;

use Tally\BenchmarkCase;

interface SuiteLoader
{

	/**
	 * @return list<BenchmarkCase> in the order of CaseId::compare()
	 * @throws InvalidBenchmarkException
	 */
	public function load(): array;

}
