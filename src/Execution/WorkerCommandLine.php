<?php declare(strict_types = 1);

namespace Tally\Execution;

use Tally\BenchmarkCase;

/**
 * The commands that run one case with tally worker: natively, and under CodSpeed's CPU simulation.
 */
final class WorkerCommandLine
{

	/**
	 * Valgrind breaks PHP's detection of the stack size, so the stack limit check is turned off.
	 */
	private const COUNTED_INI_ENTRIES = ['zend.max_allowed_stack_size=-1'];

	/**
	 * What codspeed run --mode=simulation of CodSpeed 5.6.3 passes, so the instructions match those
	 * of CodSpeed: the simulated caches, and counting only between the calls of tally_hooks. Without
	 * its shell around the command, Valgrind does not need to follow child processes. Unlike
	 * CodSpeed, Tally starts the instrumentation once and collects only in the spans, so the
	 * simulated caches stay warm between them and the cycles of small benchmarks differ.
	 */
	private const VALGRIND_OPTIONS = [
		'-q',
		'--tool=callgrind',
		'--cache-sim=yes',
		'--I1=32768,8,64',
		'--D1=32768,8,64',
		'--LL=8388608,16,64',
		'--cycle-estimation=yes',
		'--deterministic-rng=yes',
		'--collect-systime=nsec',
		'--read-inline-info=yes',
		'--instr-atstart=no',
		'--collect-atstart=no',
		'--separate-threads=no',
		'--compress-strings=no',
		'--combine-dumps=yes',
		'--dump-line=no',
	];

	/**
	 * @param string $tallyBinary the bin/tally of this installation
	 */
	public function __construct(private string $phpBinary, private string $tallyBinary)
	{
	}

	/**
	 * @return non-empty-list<string>
	 */
	public function native(WorkerSettings $settings, BenchmarkCase $case, string $scratch, string $result): array
	{
		return [
			$this->phpBinary,
			...$this->ini($settings->iniEntries),
			...$this->worker($settings, $case, $scratch, $result, []),
		];
	}

	/**
	 * The worker under Callgrind, which finds its tools through VALGRIND_LIB, with the tally_hooks
	 * extension.
	 *
	 * @return non-empty-list<string>
	 */
	public function counted(WorkerSettings $settings, BenchmarkCase $case, string $scratch, string $result, string $profileFolder): array
	{
		return [
			$settings->valgrind,
			...self::VALGRIND_OPTIONS,
			'--callgrind-out-file=' . $profileFolder . '/%p.out',
			$this->phpBinary,
			...$this->ini([
				...self::COUNTED_INI_ENTRIES,
				...($settings->extension === null ? [] : ['extension=' . $settings->extension]),
				...$settings->iniEntries,
			]),
			...$this->worker($settings, $case, $scratch, $result, ['--counted']),
		];
	}

	/**
	 * @param list<string> $entries
	 * @return list<string>
	 */
	private function ini(array $entries): array
	{
		$arguments = [];
		foreach ($entries as $entry) {
			$arguments[] = '-d';
			$arguments[] = $entry;
		}

		return $arguments;
	}

	/**
	 * The worker loads only the file of the case's class, so the cost of starting it does not grow
	 * with the suite, and the data providers of other files do not run.
	 *
	 * @param list<string> $options
	 * @return list<string>
	 */
	private function worker(WorkerSettings $settings, BenchmarkCase $case, string $scratch, string $result, array $options): array
	{
		return [
			$this->tallyBinary,
			'worker',
			'--case=' . $case->id->toString(),
			'--scratch=' . $scratch,
			'--result=' . $result,
			...$options,
			...$settings->suiteOptions,
			'--',
			$case->file,
		];
	}

}
