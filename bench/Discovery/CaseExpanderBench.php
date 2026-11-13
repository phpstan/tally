<?php declare(strict_types = 1);

namespace Tally\Bench\Discovery;

use LogicException;
use ReflectionClass;
use ReflectionMethod;
use Tally\Attribute\BeforeMethods;
use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;
use Tally\Attribute\Warmup;
use Tally\Bench\SuiteSource;
use Tally\Discovery\CaseExpander;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\ScratchDirectory;
use function class_exists;
use function file_put_contents;
use function sprintf;

/**
 * Every tally command, and every worker tally run starts for a case, loads the whole suite, so
 * the cases of each benchmark method are expanded once per case of the run.
 */
#[Warmup(1)]
#[BeforeMethods('declareClass')]
final class CaseExpanderBench
{

	/**
	 * @return iterable<string, array{int, int}> the methods and data sets of the class
	 */
	public static function classes(): iterable
	{
		foreach ([10, 100] as $size) {
			yield sprintf('size-%d', $size) => [$size, $size];
		}
	}

	public function declareClass(int $methods, int $dataSets, ScratchDirectory $scratch): void
	{
		$source = new SuiteSource();
		if (class_exists($source->className($methods, $dataSets), false)) {
			return;
		}

		$file = sprintf('%s/Suite%dx%dBench.php', $scratch->path(), $methods, $dataSets);
		file_put_contents($file, $source->code($methods, $dataSets, true));
		require $file;
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('classes')]
	#[ExpectedFor('size-10', instructions: '1.164M', cycles: '1.837M', memory: '18.72KiB')]
	#[ExpectedFor('size-100', instructions: '12.43M', cycles: '16.94M', memory: '170.6KiB')]
	public function benchExpand(int $methods, int $dataSets): void
	{
		$name = (new SuiteSource())->className($methods, $dataSets);
		if (!class_exists($name, false)) {
			throw new LogicException(sprintf('declareClass() did not declare %s.', $name));
		}

		$class = new ReflectionClass($name);
		$expander = new CaseExpander(new ExpectedAttributeRewriter());
		foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->isStatic()) {
				continue;
			}

			$expander->expand($class, $method);
		}
	}

}
