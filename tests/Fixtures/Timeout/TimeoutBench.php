<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Timeout;

use function sleep;

final class TimeoutBench
{

	public function benchSleeps(): void
	{
		sleep(30);
	}

}
