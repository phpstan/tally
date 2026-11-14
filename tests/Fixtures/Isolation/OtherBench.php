<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Isolation;

use RuntimeException;
use Tally\Attribute\DataProvider;
use function in_array;

/**
 * Its data provider fails in the workers, which load only the file of their case.
 */
final class OtherBench
{

	/**
	 * @return iterable<string, array{}>
	 */
	public static function dataSets(): iterable
	{
		if (in_array('worker', $_SERVER['argv'], true)) {
			throw new RuntimeException('A worker of another file ran this data provider.');
		}

		yield 'only' => [];
	}

	#[DataProvider('dataSets')]
	public function benchOther(): void
	{
	}

}
