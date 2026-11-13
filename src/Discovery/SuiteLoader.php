<?php declare(strict_types = 1);

namespace PHPStan\Tally\Discovery;

use PHPStan\Tally\BenchmarkCase;

interface SuiteLoader
{

	/**
	 * @return list<BenchmarkCase> in the order of CaseId::compare()
	 * @throws InvalidBenchmarkException
	 */
	public function load(): array;

}
