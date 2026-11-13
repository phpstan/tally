<?php declare(strict_types = 1);

namespace PHPStan\Tally;

/**
 * What a tally.php configuration file returns:
 *
 *     return Configuration::create()
 *         ->withPaths([__DIR__ . '/benchmarks']);
 */
final class Configuration
{

	/**
	 * @param list<string> $paths
	 */
	private function __construct(private array $paths)
	{
	}

	public static function create(): self
	{
		return new self([]);
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
	 * @return list<string>
	 */
	public function paths(): array
	{
		return $this->paths;
	}

}
