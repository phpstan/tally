<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Runner;

use Tally\Tests\FakeMemory;

/** What the fixture benchmarks and JournalCounter did, in order, for CaseRunnerTest. */
final class Journal
{

	/** @var list<string> */
	public static array $entries = [];

	public static ?FakeMemory $memory = null;

}
