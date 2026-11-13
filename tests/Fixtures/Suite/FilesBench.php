<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Suite;

use Tally\Attribute\DataProvider;
use Tally\DataSet;
use Tally\Metrics;
use Tally\Tests\InMemoryExpectations;

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
