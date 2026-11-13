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
	 * @param string $codspeed the CodSpeed CLI
	 * @param string $instrumentHooks CodSpeed's instrument-hooks library
	 * @param string $workDirectory where the cases get their scratch directories, profiles, and logs
	 */
	public function __construct(
		public readonly array $iniEntries,
		public readonly array $suiteArguments,
		public readonly string $codspeed,
		public readonly string $instrumentHooks,
		public readonly string $workDirectory,
		public readonly int $nativeTimeLimitSeconds,
		public readonly int $countedTimeLimitSeconds
	)
	{
	}

}
