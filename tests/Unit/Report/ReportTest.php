<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Report;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Attribute\DurationTolerance;
use Tally\Comparison\CaseComparison;
use Tally\Comparison\Status;
use Tally\Report\Report;
use Tally\Report\Section;
use function array_map;

final class ReportTest extends TestCase
{

	public function testGroupsTheCasesByTheirWorstStatus(): void
	{
		$report = Comparisons::mixed();

		$this->assertSame(
			[
				['slower', ['App\ABench::benchSlower@big', 'App\ABench::benchSlower@small']],
				['not measured', ['App\ABench::benchBroken']],
				['new', ['App\ABench::benchNew']],
				['faster', ['App\ABench::benchFaster']],
			],
			array_map(static fn (Section $section): array => [$section->status->value, $section->ids()], $report->sections()),
		);
		$this->assertTrue($report->fails());
		$this->assertSame('Benchmarks failed: 2 slower, 1 not measured, 1 new, 1 faster, 1 unchanged', $report->headline());
	}

	public function testPassesWithFasterAndNewCases(): void
	{
		$report = new Report([
			Comparisons::compare('A::faster', 1, ['instructions' => 10], ['instructions' => 5], null),
			Comparisons::compare('A::new', 2, null, ['instructions' => 5], null),
			Comparisons::compare('A::ok', 3, ['instructions' => 10], ['instructions' => 10], null),
			Comparisons::compare('A::ok2', 4, ['instructions' => 10], ['instructions' => 10], null),
		]);

		$this->assertFalse($report->fails());
		$this->assertSame('Benchmarks passed: 1 new, 1 faster, 2 unchanged', $report->headline());
	}

	public function testHeadlineWhenEverythingMatchesOrThereIsNothing(): void
	{
		$this->assertSame('Benchmarks passed: all 1 match their expectations', (new Report([Comparisons::compare('A::ok', 3, ['instructions' => 10], ['instructions' => 10], null)]))->headline());
		$this->assertSame('No benchmarks to compare', (new Report([]))->headline());
		$this->assertFalse((new Report([]))->fails());
		$this->assertSame([], (new Report([]))->sections());
	}

	public function testWorstIsTheCaseOfTheWorstSectionThatChangedTheMost(): void
	{
		$this->assertSame('App\ABench::benchSlower@big', Comparisons::mixed()->worst()?->id->toString());

		$report = new Report([
			Comparisons::compare('A::a', 1, ['instructions' => 10], ['instructions' => 12], null),
			// faster by more, but not in the worst section
			Comparisons::compare('A::b', 2, ['instructions' => 10], ['instructions' => 1], null),
			Comparisons::compare('A::c', 3, ['instructions' => 10], ['instructions' => 15], null),
			// as much as the worst, the first one stays
			Comparisons::compare('A::d', 4, ['instructions' => 10], ['instructions' => 15], null),
		]);
		$this->assertSame('A::c', $report->worst()?->id->toString());

		$report = new Report([
			// slower from nothing, which has no relative change
			Comparisons::compare('A::fromNothing', 1, ['memory' => 0], ['memory' => 64], null),
			Comparisons::compare('A::twenty', 2, ['instructions' => 10], ['instructions' => 12], null),
			// less than the one before, the next one is still looked at
			Comparisons::compare('A::fifteen', 3, ['instructions' => 100], ['instructions' => 115], null),
			Comparisons::compare('A::thirty', 4, ['instructions' => 10], ['instructions' => 13], null),
		]);
		$this->assertSame('A::thirty', $report->worst()?->id->toString());

		$report = new Report([
			Comparisons::compare('A::a', 1, null, null, 'It broke.'),
			Comparisons::compare('A::b', 2, null, null, 'It broke too.'),
		]);
		$this->assertSame('A::a', $report->worst()?->id->toString());
		$this->assertNull((new Report([Comparisons::compare('A::ok', 3, ['instructions' => 10], ['instructions' => 10], null)]))->worst());
	}

	public function testListsEachToleranceOnce(): void
	{
		$duration = new DurationTolerance([0 => 0.2], 1000, 1000, 0.1);
		$report = new Report([
			Comparisons::compare('A::a', 1, null, ['instructions' => 1], null),
			Comparisons::compareWith($duration, 'A::b', 2, null, ['instructions' => 1], null),
			Comparisons::compare('A::c', 3, null, ['instructions' => 1], null),
		]);

		$this->assertSame(['±10 % of each metric', $duration->describe()], $report->tolerances());
	}

	public function testSectionsSayWhatToDo(): void
	{
		$case = Comparisons::compare('A::a', 1, null, ['instructions' => 1], null);
		$texts = [];
		foreach (Status::cases() as $status) {
			$section = new Section($status, [$case]);
			$texts[] = $section->title();
		}

		$this->assertSame(['Slower than expected', 'Not measured', 'New benchmarks', 'Faster than expected', 'Unchanged'], $texts);
		$this->assertSame('These benchmarks match their expectations.', (new Section(Status::Ok, [$case]))->text());
	}

	/**
	 * @return iterable<string, array{list<CaseComparison>, string}>
	 */
	public static function dataSlowerText(): iterable
	{
		$numbers = Comparisons::compare('A::b', 1, ['instructions' => 1], ['instructions' => 2], null);
		$growing = Comparisons::grown('A::a@large', 20_000);
		$prefix = 'These benchmarks do more work than expected. Find out why before merging. If it is the price of a fix, ';
		yield 'expected numbers' => [[$numbers, $numbers], $prefix . 'accept the new numbers.'];
		yield 'growth' => [[$growing, $growing], $prefix . 'accept the new complexity class.'];
		yield 'both' => [[$numbers, $growing], $prefix . 'accept the new numbers and complexity classes.'];
	}

	/**
	 * @param non-empty-list<CaseComparison> $cases
	 */
	#[DataProvider('dataSlowerText')]
	public function testSlowerText(array $cases, string $text): void
	{
		$this->assertSame($text, (new Section(Status::Slower, $cases))->text());
	}

	/**
	 * @return iterable<string, array{list<CaseComparison>, bool}>
	 */
	public static function dataHasChangesToAccept(): iterable
	{
		yield 'nothing' => [[], false];
		yield 'unchanged' => [[Comparisons::compare('A::b', 1, ['instructions' => 1], ['instructions' => 1], null)], false];
		yield 'not measured' => [[Comparisons::compare('A::b', 1, ['instructions' => 1], null, 'It broke.')], false];
		yield 'slower' => [[Comparisons::compare('A::b', 1, ['instructions' => 1], ['instructions' => 2], null)], true];
		yield 'faster' => [[Comparisons::compare('A::b', 1, ['instructions' => 10], ['instructions' => 1], null)], true];
		yield 'new' => [[Comparisons::compare('A::b', 1, null, ['instructions' => 1], null)], true];
		yield 'a growth that is slower' => [[Comparisons::grown('A::a@large', 20_000)], true];
		yield 'a growth within its class' => [[Comparisons::grown('A::a@large', 5_000)], false];
	}

	/**
	 * @param list<CaseComparison> $cases
	 */
	#[DataProvider('dataHasChangesToAccept')]
	public function testHasChangesToAccept(array $cases, bool $has): void
	{
		$this->assertSame($has, (new Report($cases))->hasChangesToAccept());
	}

}
