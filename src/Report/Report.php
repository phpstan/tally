<?php declare(strict_types = 1);

namespace Tally\Report;

use Tally\Comparison\CaseComparison;
use Tally\Comparison\Status;
use function abs;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function max;
use function sprintf;

/**
 * The comparisons of a run, grouped into what needs attention. Each case goes into the section
 * of its worst metric, so a case slower in instructions but lower in memory is listed as slower,
 * with both metrics.
 */
final class Report
{

	/**
	 * @param list<CaseComparison> $cases in case order
	 */
	public function __construct(public readonly array $cases)
	{
	}

	/**
	 * @return list<Section> the sections with a case, from the worst status, without the unchanged cases
	 */
	public function sections(): array
	{
		$sections = [];
		foreach (Status::cases() as $status) {
			$cases = $this->withStatus($status);
			if ($status === Status::Ok || $cases === []) {
				continue;
			}

			$sections[] = new Section($status, $cases);
		}

		return $sections;
	}

	public function fails(): bool
	{
		foreach ($this->cases as $case) {
			if ($case->status->fails()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether tally accept has something to write: the numbers of a slower, faster, or new case, or
	 * the complexity class of a case that grew more than its class allows
	 */
	public function hasChangesToAccept(): bool
	{
		foreach ($this->cases as $case) {
			if ($case->status === Status::Slower || $case->acceptable && $case->status !== Status::Ok && $case->status !== Status::NotMeasured) {
				return true;
			}
		}

		return false;
	}

	public function headline(): string
	{
		if ($this->cases === []) {
			return 'No benchmarks to compare';
		}

		$sections = $this->sections();
		if ($sections === []) {
			return sprintf('Benchmarks passed: all %d match their expectations', count($this->cases));
		}

		$parts = array_map(static fn (Section $section): string => sprintf('%d %s', count($section->cases), $section->status->value), $sections);
		$parts[] = sprintf('%d unchanged', count($this->withStatus(Status::Ok)));

		return ($this->fails() ? 'Benchmarks failed: ' : 'Benchmarks passed: ') . implode(', ', $parts);
	}

	/**
	 * @return list<string> the descriptions of the tolerance policies, each once
	 */
	public function tolerances(): array
	{
		return array_values(array_unique(array_map(static fn (CaseComparison $case): string => $case->tolerance->describe(), $this->cases)));
	}

	/**
	 * The case to look at first: of the worst section, the one that changed the most.
	 */
	public function worst(): ?CaseComparison
	{
		$worst = null;
		$worstChange = -1.0;
		foreach ($this->sections()[0]->cases ?? [] as $case) {
			$change = 0.0;
			foreach ($case->metrics as $metric) {
				$change = max($change, abs($metric->change() ?? 0.0));
			}
			if ($change <= $worstChange) {
				continue;
			}

			$worst = $case;
			$worstChange = $change;
		}

		return $worst;
	}

	/**
	 * @return list<CaseComparison>
	 */
	private function withStatus(Status $status): array
	{
		return array_values(array_filter($this->cases, static fn (CaseComparison $case): bool => $case->status === $status));
	}

}
