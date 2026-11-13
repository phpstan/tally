<?php declare(strict_types = 1);

namespace Tally\Discovery;

use FilesystemIterator;
use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Report\RelativePath;
use Throwable;
use function array_filter;
use function array_map;
use function get_declared_classes;
use function in_array;
use function is_file;
use function iterator_to_array;
use function realpath;
use function sort;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function usort;

/**
 * Finds benchmarks like PHPUnit finds tests: the public, non-static methods whose names start
 * with bench, in the instantiable classes declared in *Bench.php files.
 */
final class DirectorySuiteLoader
{

	public function __construct(private CaseExpander $expander, private ConstructorArguments $constructor, private RelativePath $relativePath)
	{
	}

	/**
	 * @param list<string> $paths directories to search, and files to load
	 * @param string $workingDirectory errors name files relative to it
	 * @return list<BenchmarkCase> in the order of CaseId::compare()
	 * @throws InvalidBenchmarkException
	 */
	public function load(array $paths, string $workingDirectory): array
	{
		$files = $this->files($paths);
		foreach ($files as $file) {
			$this->require($file, $this->relativePath->of($file, $workingDirectory));
		}

		$cases = [];
		$classNames = get_declared_classes();
		// the order PHP declared them in depends on what was autoloaded before, so the first invalid benchmark would too
		sort($classNames);
		foreach ($classNames as $className) {
			$class = new ReflectionClass($className);
			if (!in_array($class->getFileName(), $files, true) || !$class->isInstantiable()) {
				continue;
			}
			$this->constructor->check($class);
			$hooks = $this->expander->hooks($class);
			foreach ($class->getMethods() as $method) {
				if (!str_starts_with($method->getName(), 'bench')) {
					continue;
				}
				if (!$method->isPublic() || $method->isStatic()) {
					throw new InvalidBenchmarkException($className . '::' . $method->getName() . '()', 'a bench* method must be public and not static. Rename it if it is not a benchmark.');
				}

				$cases = [...$cases, ...$this->expander->expand($class, $method, $hooks)];
			}
		}

		usort($cases, static fn (BenchmarkCase $a, BenchmarkCase $b): int => CaseId::compare($a->id, $b->id));

		return $cases;
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	private function require(string $file, string $name): void
	{
		try {
			// the autoloader may have loaded it already
			require_once $file;
		} catch (ParseError $e) {
			throw new InvalidBenchmarkException($name, sprintf('%s on line %d.', $e->getMessage(), $e->getLine()));
		} catch (Throwable $e) {
			throw new InvalidBenchmarkException($name, sprintf('loading it threw %s: %s', $e::class, $e->getMessage()));
		}
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
