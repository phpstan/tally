<?php declare(strict_types = 1);

namespace Tally;

/**
 * What a tally.php configuration file returns:
 *
 *     return Configuration::create()
 *         ->withPaths([__DIR__ . '/benchmarks'])
 *         ->withIniEntries(['memory_limit=-1']);
 *
 * @api
 */
final class Configuration
{

	/** A case usually takes milliseconds when it runs natively, one stuck in a loop fails quickly */
	public const NATIVE_TIME_LIMIT_SECONDS = 60;

	/** CodSpeed's CPU simulation runs a case about 50 times slower than natively */
	public const COUNTED_TIME_LIMIT_SECONDS = 30 * 60;

	/**
	 * @param list<string> $paths
	 * @param list<string> $iniEntries
	 */
	private function __construct(
		private array $paths,
		private array $iniEntries,
		private int $nativeTimeLimitSeconds,
		private int $countedTimeLimitSeconds
	)
	{
	}

	public static function create(): self
	{
		return new self([], [], self::NATIVE_TIME_LIMIT_SECONDS, self::COUNTED_TIME_LIMIT_SECONDS);
	}

	/**
	 * @param list<string> $paths directories with *Bench.php files, and single files
	 */
	public function withPaths(array $paths): self
	{
		$configuration = clone $this;
		$configuration->paths = $paths;

		return $configuration;
	}

	/**
	 * @param list<string> $iniEntries PHP settings the cases run with, like memory_limit=-1
	 */
	public function withIniEntries(array $iniEntries): self
	{
		$configuration = clone $this;
		$configuration->iniEntries = $iniEntries;

		return $configuration;
	}

	/**
	 * A case that runs longer is killed and reported as not measured.
	 *
	 * @param int $nativeSeconds of the native run that precedes the counted one
	 * @param int $countedSeconds of the run under CodSpeed's CPU simulation
	 */
	public function withTimeLimits(int $nativeSeconds, int $countedSeconds): self
	{
		$configuration = clone $this;
		$configuration->nativeTimeLimitSeconds = $nativeSeconds;
		$configuration->countedTimeLimitSeconds = $countedSeconds;

		return $configuration;
	}

	/**
	 * @return list<string>
	 */
	public function paths(): array
	{
		return $this->paths;
	}

	/**
	 * @return list<string>
	 */
	public function iniEntries(): array
	{
		return $this->iniEntries;
	}

	public function nativeTimeLimitSeconds(): int
	{
		return $this->nativeTimeLimitSeconds;
	}

	public function countedTimeLimitSeconds(): int
	{
		return $this->countedTimeLimitSeconds;
	}

}
