<?php declare(strict_types = 1);

namespace PHPStan\Tally\Discovery\data\suite\Nested;

use PHPStan\Tally\Attribute\DataProvider;

final class IndexedBench
{

	/**
	 * @return list<array{int}>
	 */
	public static function values(): array
	{
		return [[1], [2]];
	}

	#[DataProvider('values')]
	public function benchIndexed(int $value): void
	{
	}

}
