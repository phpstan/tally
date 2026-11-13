<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Growth;

use Tally\Attribute\DataProvider;
use Tally\Attribute\Linear;

final class GrowthBench
{

	public static function lists(): iterable
	{
		// large runs first, before small, its base
		yield 'large' => [[1, 2, 3, 4]];
		yield 'small' => [[1, 2]];
	}

	#[DataProvider('lists')]
	#[Linear('numbers')]
	public function benchLinear(array $numbers): void
	{
	}

}
