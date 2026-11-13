<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Loading\Syntax;

final class BrokenBench
{

	public function benchSort(): void
	{
		sort([3, 1, 2];
	}

}
