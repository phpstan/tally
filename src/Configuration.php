<?php declare(strict_types = 1);

namespace Tally;

use Tally\Attribute\MetricTolerance;
use Tally\Attribute\RelativeTolerance;
use Tally\Comparison\TolerancePolicy;

/**
 * What a tally.php configuration file returns:
 *
 *     return Configuration::create()
 *         ->withPaths([__DIR__ . '/benchmarks'])
 *         ->withIniEntries(['memory_limit=-1']);
 *
 * Every value Tally uses when a benchmark or the command line does not give one is chosen here.
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
		private int $countedTimeLimitSeconds,
		private TolerancePolicy $tolerance,
		private TolerancePolicy $growthTolerance,
		private bool $namedArguments
	)
	{
	}

	public static function create(): self
	{
		return new self(
			[],
			[],
			self::NATIVE_TIME_LIMIT_SECONDS,
			self::COUNTED_TIME_LIMIT_SECONDS,
			// The same code counts the same instructions to a few dozen, so 2 % is a real change. The
			// cycles of a benchmark of a few thousand instructions move by a few cache misses when
			// unrelated code changes, up to about 1,300 cycles in PHPStan's benchmarks.
			new MetricTolerance(instructions: 0.02, cycles: 0.05, memory: 0.05, cyclesAtLeast: 2000),
			// A complexity class only bounds how the instructions grow; the code around the part that
			// grows makes the counts of PHPStan's benchmarks up to 9 % more than the formula.
			new RelativeTolerance(0.1),
			false,
		);
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
	 * The tolerance of the benchmarks without a tolerance policy of their method or class.
	 */
	public function withTolerance(TolerancePolicy $tolerance): self
	{
		$configuration = clone $this;
		$configuration->tolerance = $tolerance;

		return $configuration;
	}

	/**
	 * The tolerance of the benchmarks with a complexity class and without a tolerance policy of their
	 * method or class: how far the instructions may exceed what the formula allows.
	 */
	public function withGrowthTolerance(TolerancePolicy $tolerance): self
	{
		$configuration = clone $this;
		$configuration->growthTolerance = $tolerance;

		return $configuration;
	}

	/**
	 * Whether tally accept and the patch of new expectations write the data set of #[ExpectedFor] as
	 * the named argument dataSet: '…' instead of the first argument.
	 */
	public function withNamedArguments(bool $namedArguments): self
	{
		$configuration = clone $this;
		$configuration->namedArguments = $namedArguments;

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

	public function tolerance(): TolerancePolicy
	{
		return $this->tolerance;
	}

	public function growthTolerance(): TolerancePolicy
	{
		return $this->growthTolerance;
	}

	public function namedArguments(): bool
	{
		return $this->namedArguments;
	}

}
