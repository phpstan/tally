<?php declare(strict_types = 1);

namespace Tally\Discovery;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use Tally\BenchmarkCase;
use Tally\CaseId;
use function array_filter;
use function array_map;
use function get_declared_classes;
use function in_array;
use function is_file;
use function iterator_to_array;
use function realpath;
use function str_ends_with;
use function str_starts_with;
use function usort;

/**
 * Finds benchmarks like PHPUnit finds tests: the public, non-static methods whose names start
 * with bench, in the instantiable classes declared in *Bench.php files.
 */
final class DirectorySuiteLoader
{

	public function __construct(private CaseExpander $expander)
	{
	}

	/**
	 * @param list<string> $paths directories to search, and files to load
	 * @return list<BenchmarkCase> in the order of CaseId::compare()
	 * @throws InvalidBenchmarkException
	 */
	public function load(array $paths): array
	{
		$files = $this->files($paths);
		foreach ($files as $file) {
			// the autoloader may have loaded it already
			require_once $file;
		}

		$cases = [];
		foreach (get_declared_classes() as $className) {
			$class = new ReflectionClass($className);
			if (!in_array($class->getFileName(), $files, true) || !$class->isInstantiable()) {
				continue;
			}
			foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
				if ($method->isStatic() || !str_starts_with($method->getName(), 'bench')) {
					continue;
				}

				$cases = [...$cases, ...$this->expander->expand($class, $method)];
			}
		}

		usort($cases, static fn (BenchmarkCase $a, BenchmarkCase $b): int => CaseId::compare($a->id, $b->id));

		return $cases;
	}

	/**
	 * @param list<string> $paths
	 * @return list<string>
	 * @throws InvalidBenchmarkException
	 */
	private function files(array $paths): array
	{
		$files = [];
		foreach ($paths as $path) {
			$realPath = realpath($path);
			if ($realPath === false) {
				throw new InvalidBenchmarkException($path, 'the path does not exist.');
			}
			if (is_file($realPath)) {
				$files[] = $realPath;
				continue;
			}

			$found = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS));
			$benchFiles = array_filter(
				iterator_to_array($found, false),
				static fn (mixed $file): bool => $file instanceof SplFileInfo && str_ends_with($file->getFilename(), 'Bench.php'),
			);
			$files = [...$files, ...array_map(static fn (SplFileInfo $file): string => $file->getPathname(), $benchFiles)];
		}

		// load() sorts the cases
		return $files;
	}

}
