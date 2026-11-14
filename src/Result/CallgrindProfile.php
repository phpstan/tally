<?php declare(strict_types = 1);

namespace Tally\Result;

use Tally\Metric;
use Tally\Metrics;
use function array_combine;
use function array_map;
use function count;
use function explode;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Reads the callgrind profile CodSpeed's CPU simulation writes for each process. Every time the
 * benchmark stops counting, tally_hooks dumps a part named after the case.
 *
 * Estimated cycles use Valgrind's cycle estimation: instructions, plus 10 for each first-level
 * cache miss, plus 100 for each last-level cache miss. CodSpeed simulates the same cache sizes on
 * every machine, so the misses do not depend on the CPU of the machine.
 */
final class CallgrindProfile
{

	private const NAME_PREFIX = 'desc: Trigger: Client Request: ';

	/**
	 * @return array<string, Metrics> instructions and cycles keyed by the name of the part
	 */
	public function parts(string $contents): array
	{
		$parts = [];
		$name = null;
		$events = [];
		foreach (explode("\n", $contents) as $line) {
			if (str_starts_with($line, 'part:')) {
				$name = null;
			} elseif (str_starts_with($line, self::NAME_PREFIX)) {
				$name = substr($line, strlen(self::NAME_PREFIX));
			} elseif (str_starts_with($line, 'events:')) {
				$events = $this->fields($line, 'events:');
			} elseif ($name !== null && str_starts_with($line, 'totals:')) {
				$values = array_map('intval', $this->fields($line, 'totals:'));
				if (count($values) !== count($events)) {
					continue;
				}

				$parts[$name] = $this->metrics(array_combine($events, $values));
			}
		}

		return $parts;
	}

	/**
	 * @param array<string, int> $totals
	 */
	private function metrics(array $totals): Metrics
	{
		$instructions = $totals['Ir'] ?? 0;
		$firstLevelMisses = ($totals['I1mr'] ?? 0) + ($totals['D1mr'] ?? 0) + ($totals['D1mw'] ?? 0);
		$lastLevelMisses = ($totals['ILmr'] ?? 0) + ($totals['DLmr'] ?? 0) + ($totals['DLmw'] ?? 0);

		return Metrics::none()
			->with(Metric::Instructions, $instructions)
			->with(Metric::Cycles, $instructions + 10 * $firstLevelMisses + 100 * $lastLevelMisses);
	}

	/**
	 * @return list<string> the fields after the line's label
	 */
	private function fields(string $line, string $label): array
	{
		return explode(' ', trim(substr($line, strlen($label))));
	}

}
