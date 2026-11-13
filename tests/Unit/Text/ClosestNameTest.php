<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Text;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Text\ClosestName;

final class ClosestNameTest extends TestCase
{

	/**
	 * @return iterable<string, array{string, list<string>, string|null}>
	 */
	public static function dataFind(): iterable
	{
		$commands = ['list', 'run', 'compare', 'accept'];
		yield 'a letter missing' => ['rn', $commands, 'run'];
		yield 'two letters swapped' => ['lsit', $commands, 'list'];
		yield 'a letter too many' => ['acccept', $commands, 'accept'];
		yield 'case differs' => ['RUN', $commands, 'run'];
		yield 'case differs in the candidate' => ['run', ['RUN'], 'RUN'];
		yield 'two edits away' => ['comapre', $commands, 'compare'];
		yield 'three edits away' => ['cmpe', $commands, null];
		yield 'nothing alike' => ['deploy', $commands, null];
		yield 'the closest of several' => ['fltr', ['file', 'filter', 'shard'], 'filter'];
		yield 'the first of equally close' => ['bat', ['cat', 'hat'], 'cat'];
		yield 'no candidates' => ['run', [], null];
	}

	/**
	 * @param list<string> $candidates
	 */
	#[DataProvider('dataFind')]
	public function testFind(string $name, array $candidates, ?string $closest): void
	{
		$this->assertSame($closest, (new ClosestName())->find($name, $candidates));
	}

}
