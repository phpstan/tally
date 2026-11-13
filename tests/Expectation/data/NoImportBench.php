<?php declare(strict_types = 1);

namespace PHPStan\Tally\Expectation\data;

use function sprintf;

final class NoImportBench
{

	public function benchSprintf(): void
	{
		sprintf('%d', 1);
	}

}
