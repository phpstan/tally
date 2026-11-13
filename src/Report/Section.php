<?php declare(strict_types = 1);

namespace Tally\Report;

use Tally\Comparison\CaseComparison;
use Tally\Comparison\Status;
use function array_map;
use function count;

/** The cases of one status in a report, with what the report says about them */
final class Section
{

	/**
	 * @param non-empty-list<CaseComparison> $cases
	 */
	public function __construct(public readonly Status $status, public readonly array $cases)
	{
	}

	public function title(): string
	{
		return match ($this->status) {
			Status::Slower => 'Slower than expected',
			Status::NotMeasured => 'Not measured',
			Status::New => 'New benchmarks',
			Status::Faster => 'Faster than expected',
			Status::Ok => 'Unchanged',
		};
	}

	public function text(): string
	{
		return match ($this->status) {
			Status::Slower => $this->slowerText(),
			Status::NotMeasured => 'These benchmarks failed, timed out, or did not run. Their errors are below.',
			Status::New => 'These benchmarks have no expected numbers yet. This does not fail, but accept the new numbers.',
			Status::Faster => 'Nice! This does not fail, but accept the new numbers, so a later slowdown back to the old ones does not go unnoticed.',
			Status::Ok => 'These benchmarks match their expectations.',
		};
	}

	/** A case compared by its growth has no numbers to accept, its complexity class is what changes */
	private function slowerText(): string
	{
		$growing = 0;
		foreach ($this->cases as $case) {
			$growing += $case->acceptable ? 0 : 1;
		}

		return 'These benchmarks do more work than expected. Find out why before merging. If it is the price of a fix, ' . match ($growing) {
			0 => 'accept the new numbers.',
			count($this->cases) => 'loosen the complexity class.',
			default => 'accept the new numbers or loosen the complexity class.',
		};
	}

	/**
	 * @return list<string>
	 */
	public function ids(): array
	{
		return array_map(static fn (CaseComparison $case): string => $case->id->toString(), $this->cases);
	}

}
