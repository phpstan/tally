<?php declare(strict_types = 1);

namespace Tally\Execution;

/**
 * How tally run starts the workers of its cases.
 */
final class WorkerSettings
{

	/**
	 * @param list<string> $iniEntries PHP settings of the workers, like memory_limit=-1
	 * @param list<string> $suiteArguments the options and paths with which the workers load the suite
	 * @param string $valgrind the launcher of CodSpeed's Valgrind
	 * @param string|null $extension the tally_hooks extension the counted worker loads, null when PHP
	 *     loads it already
	 * @param string $workDirectory where the cases get their scratch directories, profiles, and logs
	 */
	public function __construct(
		public readonly array $iniEntries,
		public readonly array $suiteArguments,
		public readonly string $valgrind,
		public readonly ?string $extension,
		public readonly string $workDirectory,
		public readonly int $nativeTimeLimitSeconds,
		public readonly int $countedTimeLimitSeconds
	)
	{
	}

}
