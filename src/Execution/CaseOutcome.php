<?php declare(strict_types = 1);

namespace Tally\Execution;

use Tally\CaseId;
use Tally\Measurement;
use Tally\Metrics;
use Tally\Runner\CaseRunner;
use function array_last;
use function array_map;
use function array_slice;
use function count;
use function explode;
use function implode;
use function intdiv;
use function rtrim;
use function sprintf;

/**
 * Turns what the workers of a case left behind into its measurement: the first failure of a run,
 * or the counted parts of CodSpeed's profile with the memory the counted worker measured.
 */
final class CaseOutcome
{

	/** The last lines of a failed process's output that go into the failure */
	public const OUTPUT_LINES_LIMIT = 20;

	/**
	 * @param non-empty-list<WorkerRun> $runs in the order they ran, the counted one last
	 * @param array<string, Metrics> $parts the parts of CodSpeed's profile by name
	 */
	public function measurement(CaseId $id, array $runs, array $parts): Measurement
	{
		$written = array_map(fn (WorkerRun $run): Measurement => $this->written($id, $run), $runs);
		foreach ($written as $measurement) {
			if ($measurement->failure !== null) {
				return $measurement;
			}
		}

		$name = $id->toString();
		$counted = $parts[$name] ?? null;
		$baseline = $parts[$name . CaseRunner::BASELINE_SUFFIX] ?? null;
		if ($counted === null || $baseline === null) {
			return Measurement::failed($id, sprintf("CodSpeed's profile has no part named %s.", $counted === null ? $name : $name . CaseRunner::BASELINE_SUFFIX));
		}

		// the counted worker measured the memory, CodSpeed counted the rest
		return Measurement::measured($id, $counted->minus($baseline)->merge(array_last($written)->metrics));
	}

	/**
	 * @return Measurement what the worker wrote, or why the run failed
	 */
	private function written(CaseId $id, WorkerRun $run): Measurement
	{
		if ($run->result->timedOut) {
			return Measurement::failed($id, sprintf('%s did not finish in %s.', $run->name, $this->duration($run->result->command->timeLimitSeconds)));
		}
		if ($run->written?->failure !== null) {
			return $run->written;
		}
		if ($run->result->exitCode !== 0) {
			return Measurement::failed($id, sprintf('%s exited with code %d.', $run->name, $run->result->exitCode) . $this->tail($run->output));
		}
		if ($run->written === null) {
			return Measurement::failed($id, sprintf('%s wrote no result.', $run->name) . $this->tail($run->output));
		}

		return $run->written;
	}

	private function duration(int $seconds): string
	{
		if ($seconds % 60 !== 0) {
			return sprintf('%d %s', $seconds, $seconds === 1 ? 'second' : 'seconds');
		}

		$minutes = intdiv($seconds, 60);

		return sprintf('%d %s', $minutes, $minutes === 1 ? 'minute' : 'minutes');
	}

	/** The last lines of the output on the next lines, nothing for no output */
	private function tail(string $output): string
	{
		$output = rtrim($output);
		if ($output === '') {
			return '';
		}

		$lines = explode("\n", $output);
		$tail = array_slice($lines, -self::OUTPUT_LINES_LIMIT);
		$skipped = count($lines) - count($tail);

		return "\n" . ($skipped > 0 ? sprintf("(%d lines before)\n", $skipped) : '') . implode("\n", $tail);
	}

}
