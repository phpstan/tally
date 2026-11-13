<?php declare(strict_types = 1);

namespace Tally\Discovery;

use FilesystemIterator;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use Tally\BenchmarkCase;
use Tally\CaseId;
use function array_values;
use function class_exists;
use function count;
use function file_get_contents;
use function is_dir;
use function is_file;
use function realpath;
use function sort;
use function str_ends_with;
use function str_starts_with;
use function usort;
use const SORT_STRING;
use const T_CLASS;
use const T_DOUBLE_COLON;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NEW;
use const T_STRING;

/**
 * Finds benchmarks like PHPUnit finds tests: the public, non-static methods whose names start
 * with bench, in the instantiable classes declared in *Bench.php files.
 */
final class DirectorySuiteLoader implements SuiteLoader
{

	/**
	 * @param list<string> $paths directories to search, and files to load
	 */
	public function __construct(private array $paths, private CaseExpander $expander)
	{
	}

	public function load(): array
	{
		$cases = [];
		foreach ($this->files() as $file) {
			foreach ($this->classesIn($file) as $className) {
				if (!class_exists($className, false)) {
					require_once $file;
				}
				if (!class_exists($className, false)) {
					continue;
				}

				$class = new ReflectionClass($className);
				if (!$class->isInstantiable()) {
					continue;
				}
				foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
					if ($method->isStatic() || !str_starts_with($method->getName(), 'bench')) {
						continue;
					}

					$cases = [...$cases, ...$this->expander->expand($class, $method)];
				}
			}
		}

		usort($cases, static fn (BenchmarkCase $a, BenchmarkCase $b): int => CaseId::compare($a->id, $b->id));

		return $cases;
	}

	/**
	 * @return list<string>
	 * @throws InvalidBenchmarkException
	 */
	private function files(): array
	{
		$files = [];
		foreach ($this->paths as $path) {
			$realPath = realpath($path);
			if ($realPath !== false && is_file($realPath)) {
				$files[] = $realPath;
				continue;
			}
			if ($realPath === false || !is_dir($realPath)) {
				throw new InvalidBenchmarkException($path, 'the path does not exist.');
			}

			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $file) {
				if (!$file instanceof SplFileInfo || !$file->isFile() || !str_ends_with($file->getFilename(), 'Bench.php')) {
					continue;
				}

				$files[] = $file->getPathname();
			}
		}

		sort($files, SORT_STRING);

		return $files;
	}

	/**
	 * Reads the class names from the code, so a file is not loaded twice when the autoloader already loaded it.
	 *
	 * @return list<string>
	 */
	private function classesIn(string $file): array
	{
		$tokens = array_values(PhpToken::tokenize((string) file_get_contents($file)));
		$significant = [];
		foreach ($tokens as $token) {
			if ($token->isIgnorable()) {
				continue;
			}

			$significant[] = $token;
		}

		$namespace = '';
		$classes = [];
		$count = count($significant);
		for ($i = 0; $i < $count; $i++) {
			$token = $significant[$i];
			$next = $significant[$i + 1] ?? null;
			if ($token->is(T_NAMESPACE) && $next !== null && $next->is([T_STRING, T_NAME_QUALIFIED])) {
				$namespace = $next->text . '\\';
				continue;
			}
			if (!$token->is(T_CLASS) || $next === null || !$next->is(T_STRING)) {
				continue;
			}
			$previous = $significant[$i - 1] ?? null;
			if ($previous !== null && $previous->is([T_DOUBLE_COLON, T_NEW])) {
				continue;
			}

			$classes[] = $namespace . $next->text;
		}

		return $classes;
	}

}
