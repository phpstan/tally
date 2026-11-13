<?php declare(strict_types = 1);

namespace Tally\Cli;

use InvalidArgumentException;
use Tally\BenchmarkCase;
use Tally\Configuration;
use Tally\Discovery\CaseExpander;
use Tally\Discovery\DirectorySuiteLoader;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Selection\FilterSelector;
use Tally\Selection\ShardSelector;
use function getcwd;
use function is_file;
use function sprintf;

/**
 * The options every command that works with cases has: which benchmarks to load, and which of their cases to keep.
 */
final class SelectedCases
{

	public const DEFAULT_CONFIGURATION = 'tally.php';

	/**
	 * @return list<Option>
	 */
	public static function options(): array
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
	public static function load(Input $input): array
	{
		$paths = $input->arguments();
		if ($paths === []) {
			$paths = self::configuration($input)->paths();
		}
		if ($paths === []) {
			throw new UsageException(sprintf('No benchmarks to load. Pass their paths, or list them in %s.', self::DEFAULT_CONFIGURATION));
		}

		$cases = (new DirectorySuiteLoader($paths, new CaseExpander()))->load();

		try {
			$filter = $input->option('filter');
			if ($filter !== null) {
				$cases = (new FilterSelector($filter))->select($cases);
			}
			$shard = $input->option('shard');
			if ($shard !== null) {
				$cases = ShardSelector::fromString($shard)->select($cases);
			}
		} catch (InvalidArgumentException $e) {
			throw new UsageException($e->getMessage(), 0, $e);
		}

		return $cases;
	}

	/**
	 * @throws UsageException
	 */
	private static function configuration(Input $input): Configuration
	{
		$file = $input->option('config');
		if ($file === null) {
			$file = getcwd() . '/' . self::DEFAULT_CONFIGURATION;
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
