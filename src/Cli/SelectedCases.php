<?php declare(strict_types = 1);

namespace Tally\Cli;

use Closure;
use InvalidArgumentException;
use Tally\BenchmarkCase;
use Tally\Configuration;
use Tally\Discovery\DirectorySuiteLoader;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Selection\CaseSelector;
use Tally\Selection\FilterSelector;
use Tally\Selection\ShardSelector;
use function array_map;
use function escapeshellarg;
use function implode;
use function is_file;
use function sprintf;

/**
 * The options every command that works with cases has: which benchmarks to load, and which of their cases to keep.
 */
final class SelectedCases
{

	public const DEFAULT_CONFIGURATION = 'tally.php';
	private const NO_BENCHMARKS = 'Benchmarks are the public, non-static bench* methods of classes in *Bench.php files.';

	public function __construct(private DirectorySuiteLoader $loader, private string $workingDirectory)
	{
	}

	/**
	 * @return list<Option> the options that load the benchmarks
	 */
	public function suiteOptions(): array
	{
		return [
			new Option('config', 'file', sprintf('Configuration file, %s in the working directory by default', self::DEFAULT_CONFIGURATION)),
		];
	}

	/**
	 * @return list<Option> the options that keep some of the cases
	 */
	public function selectionOptions(): array
	{
		return [
			new Option('filter', 'filter', 'Only cases whose ID contains this text, or matches it as a regular expression like /@size-10$/'),
			new Option('shard', 'shard', 'Only the cases of one shard, like 2/4, balanced by expected instructions'),
		];
	}

	/**
	 * @param list<string> $paths the benchmarks to load, those of the configuration when empty
	 * @return list<BenchmarkCase>
	 * @throws UsageException
	 * @throws InvalidBenchmarkException
	 * @throws NoBenchmarksException
	 */
	public function load(Input $input, Configuration $configuration, array $paths): array
	{
		if ($paths === []) {
			$paths = $configuration->paths();
		}
		if ($paths === []) {
			throw new UsageException(sprintf('No benchmarks to load. Pass their paths, or list them in %s.', self::DEFAULT_CONFIGURATION));
		}

		$cases = $this->loader->load($paths, $this->workingDirectory, $configuration);
		if ($cases === []) {
			throw new NoBenchmarksException(sprintf("No benchmarks in %s.\n%s", implode(', ', array_map(escapeshellarg(...), $paths)), self::NO_BENCHMARKS));
		}

		$filter = $input->option('filter');
		if ($filter !== null) {
			$cases = $this->select(static fn (): CaseSelector => new FilterSelector($filter), $cases);
			if ($cases === []) {
				throw new UsageException(sprintf('No case matches --filter=%s.', $filter));
			}
		}
		$shard = $input->option('shard');
		if ($shard !== null) {
			// a shard may have no cases, when there are more shards than cases
			$cases = $this->select(static fn (): CaseSelector => ShardSelector::fromString($shard), $cases);
		}

		return $cases;
	}

	/**
	 * @param Closure(): CaseSelector $selector
	 * @param list<BenchmarkCase> $cases
	 * @return list<BenchmarkCase>
	 * @throws UsageException
	 */
	private function select(Closure $selector, array $cases): array
	{
		try {
			return $selector()->select($cases);
		} catch (InvalidArgumentException $e) {
			throw new UsageException($e->getMessage(), previous: $e);
		}
	}

	/**
	 * @throws UsageException
	 */
	public function configuration(Input $input): Configuration
	{
		$file = $input->option('config');
		if ($file === null) {
			$file = $this->workingDirectory . '/' . self::DEFAULT_CONFIGURATION;
			if (!is_file($file)) {
				return Configuration::create();
			}
		} elseif (!is_file($file)) {
			throw new UsageException(sprintf('Configuration file %s does not exist.', $file));
		}

		$configuration = (static fn (string $file): mixed => require $file)($file);
		if (!$configuration instanceof Configuration) {
			throw new UsageException(sprintf('Configuration file %s must return %s.', $file, Configuration::class));
		}

		return $configuration;
	}

}
