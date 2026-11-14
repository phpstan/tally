<?php declare(strict_types = 1);

namespace Tally\Execution;

use Tally\CaseId;

/**
 * The commands that run one case with tally worker: natively, and under CodSpeed's CPU simulation.
 */
final class WorkerCommandLine
{

	/**
	 * Valgrind breaks PHP's detection of the stack size, so the stack limit check is turned off.
	 */
	private const COUNTED_INI_ENTRIES = ['ffi.enable=1', 'zend.max_allowed_stack_size=-1'];

	/**
	 * What codspeed run --mode=simulation of CodSpeed 5.6.3 passes, so the counts match those of
	 * CodSpeed: the simulated caches, and counting only between the calls of instrument-hooks.
	 * Without its shell around the command, Valgrind does not need to follow child processes.
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
	public function native(WorkerSettings $settings, CaseId $id, string $scratch, string $result): array
	{
		return [
			$this->phpBinary,
			...$this->ini($settings->iniEntries),
			...$this->worker($settings, $id, $scratch, $result, []),
		];
	}

	/**
	 * The worker under Callgrind, which finds its tools through VALGRIND_LIB.
	 *
	 * @return non-empty-list<string>
	 */
	public function counted(WorkerSettings $settings, CaseId $id, string $scratch, string $result, string $profileFolder): array
	{
		return [
			$settings->valgrind,
			...self::VALGRIND_OPTIONS,
			'--callgrind-out-file=' . $profileFolder . '/%p.out',
			$this->phpBinary,
			...$this->ini([...self::COUNTED_INI_ENTRIES, ...$settings->iniEntries]),
			...$this->worker($settings, $id, $scratch, $result, ['--instrument-hooks=' . $settings->instrumentHooks]),
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
	 * @param list<string> $options
	 * @return list<string>
	 */
	private function worker(WorkerSettings $settings, CaseId $id, string $scratch, string $result, array $options): array
	{
		return [
			$this->tallyBinary,
			'worker',
			'--case=' . $id->toString(),
			'--scratch=' . $scratch,
			'--result=' . $result,
			...$options,
			// last, they can end with paths after --
			...$settings->suiteArguments,
		];
	}

}
