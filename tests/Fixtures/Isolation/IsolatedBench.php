<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Isolation;

use Tally\Attribute\Expected;

final class IsolatedBench
{

	#[Expected(instructions: '1K', cycles: '1.5K', memory: '0B')]
	public function benchOk(): void
	{
	}

}
