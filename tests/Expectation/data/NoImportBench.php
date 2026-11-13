<?php declare(strict_types = 1);

namespace Tally\Expectation\data;

use function sprintf;

final class NoImportBench
{

	public function benchSprintf(): void
	{
		sprintf('%d', 1);
	}

}
