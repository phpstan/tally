<?php declare(strict_types = 1);

namespace PHPStan\Tally\Discovery\data\suite;

use PHPStan\Tally\Attribute\DataProvider;
use PHPStan\Tally\DataSet;
use PHPStan\Tally\Expectation\InMemoryExpectations;
use PHPStan\Tally\Metrics;

final class FilesBench
{

	/**
	 * @return iterable<DataSet>
	 */
	public static function files(): iterable
	{
		yield DataSet::named('b.php', ['/b.php'])->withExpectations(new InMemoryExpectations(Metrics::fromArray(['instructions' => 2])));
		yield DataSet::named('a.php', ['/a.php']);
	}

	#[DataProvider('files')]
	public function benchFile(string $file): void
	{
	}

}
