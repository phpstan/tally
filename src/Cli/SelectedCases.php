<?php declare(strict_types = 1);

namespace Tally\Cli;

use InvalidArgumentException;
use Tally\BenchmarkCase;
use Tally\Configuration;
use Tally\Discovery\DirectorySuiteLoader;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Selection\CaseSelector;
use Tally\Selection\FilterSelector;
use Tally\Selection\ShardSelector;
use function is_file;
use function sprintf;

/**
 * The options every command that works with cases has: which benchmarks to load, and which of their cases to keep.
 */
final class SelectedCases
{

	public const DEFAULT_CONFIGURATION = 'tally.php';

	public function __construct(private DirectorySuiteLoader $loader, private string $workingDirectory)
	{
	}

	/**
	 * @return list<Option>
	 */
	public function options(): array
	{
		return [
			new Option('config', 'file', sprintf('Configuration file, %s in the working directory by default', self::DEFAULT_CONFIGURATION)),
			new Option('filter', 'filter', 'Only cases whose ID contains this text, or matches it as a regular expression like /@size-10$/'),
			new Option('shard', 'shard', 'Only the cases of one shard, like 2/4, balanced by expected instructions'),
		];
	}

	/**
	 * @return list<BenchmarkCase>
	 * @throws UsageException
	 * @throws InvalidBenchmarkException
	 */
	public function load(Input $input): array
	{
		$paths = $input->arguments();
		if ($paths === []) {
			$paths = $this->configuration($input)->paths();
		}
		if ($paths === []) {
			throw new UsageException(sprintf('No benchmarks to load. Pass their paths, or list them in %s.', self::DEFAULT_CONFIGURATION));
		}

		$cases = $this->loader->load($paths);

		foreach ($this->selectors($input) as $selector) {
			$cases = $selector->select($cases);
		}

		return $cases;
	}

	/**
	 * @return list<CaseSelector> filters before shards, so that every shard of a filter has some cases
	 * @throws UsageException
	 */
	private function selectors(Input $input): array
	{
		$selectors = [];
		try {
			$filter = $input->option('filter');
			if ($filter !== null) {
				$selectors[] = new FilterSelector($filter);
			}
			$shard = $input->option('shard');
			if ($shard !== null) {
				$selectors[] = ShardSelector::fromString($shard);
			}
		} catch (InvalidArgumentException $e) {
			throw new UsageException($e->getMessage(), previous: $e);
		}

		return $selectors;
	}

	/**
	 * @throws UsageException
	 */
	private function configuration(Input $input): Configuration
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
