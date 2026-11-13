<?php declare(strict_types = 1);

namespace Tally\Execution;

use Tally\CaseId;
use function array_map;
use function escapeshellarg;

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
	 * CodSpeed joins the command after -- into a shell script, so its arguments are escaped.
	 *
	 * @return non-empty-list<string>
	 */
	public function counted(WorkerSettings $settings, CaseId $id, string $scratch, string $result, string $profileFolder): array
	{
		$worker = [
			$this->phpBinary,
			...$this->ini([...self::COUNTED_INI_ENTRIES, ...$settings->iniEntries]),
			...$this->worker($settings, $id, $scratch, $result, ['--instrument-hooks=' . $settings->instrumentHooks]),
		];

		return [
			$settings->codspeed,
			'run',
			'--mode=simulation',
			// tally compares the numbers itself
			'--skip-upload',
			'--profile-folder=' . $profileFolder,
			'--',
			...array_map(escapeshellarg(...), $worker),
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
