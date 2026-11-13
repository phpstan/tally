<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Loading\Static;

final class StaticBench
{

	public static function benchmarks(): iterable
	{
		yield [1];
	}

}
