<?php declare(strict_types = 1);

namespace Tally\Bench\Discovery;

use LogicException;
use ReflectionClass;
use ReflectionMethod;
use Tally\Attribute\Before;
use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;
use Tally\Attribute\Warmup;
use Tally\Bench\SuiteSource;
use Tally\Configuration;
use Tally\Discovery\ArgumentResolver;
use Tally\Discovery\AttributeFactory;
use Tally\Discovery\CaseExpander;
use Tally\Discovery\GrowthPoints;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Discovery\ValueTypeCheck;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Expression\Evaluator;
use Tally\Expression\ExpressionErrors;
use Tally\Expression\FunctionLibrary;
use Tally\Expression\Functions\Exponential;
use Tally\Expression\Functions\Factorial;
use Tally\Expression\Functions\Log;
use Tally\Expression\Functions\SquareRoot;
use Tally\Expression\Lexer;
use Tally\Expression\Parser;
use Tally\Expression\TypeChecker;
use Tally\ScratchDirectory;
use Tally\Text\ClosestName;
use function class_exists;
use function file_put_contents;
use function sprintf;

/**
 * Every tally command, and every worker tally run starts for a case, loads the whole suite, so
 * the cases of each benchmark method are expanded once per case of the run.
 */
#[Warmup(1)]
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

	private CaseExpander $expander;

	private Configuration $configuration;

	public function __construct(private ScratchDirectory $scratch)
	{
		$functions = new FunctionLibrary([new Log(), new SquareRoot(), new Exponential(), new Factorial()]);
		$errors = new ExpressionErrors(new ClosestName());
		$this->configuration = Configuration::create();
		$this->expander = new CaseExpander(
			new ExpectedAttributeRewriter(),
			new AttributeFactory(new ValueTypeCheck()),
			new ArgumentResolver(),
			new GrowthPoints(new Parser(new Lexer()), new TypeChecker($functions, $errors), new Evaluator($functions, $errors)),
		);
	}

	#[Before]
	public function declareClass(int $methods, int $dataSets): void
	{
		$source = new SuiteSource();
		if (class_exists($source->className($methods, $dataSets), false)) {
			return;
		}

		$file = sprintf('%s/Suite%dx%dBench.php', $this->scratch->path(), $methods, $dataSets);
		file_put_contents($file, $source->code($methods, $dataSets, true));
		require $file;
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('classes')]
	#[ExpectedFor('size-10', instructions: '1.835M', cycles: '2.451M', memory: '19.59KiB')]
	#[ExpectedFor('size-100', instructions: '19.02M', cycles: '25.07M', memory: '174.3KiB')]
	public function benchExpand(int $methods, int $dataSets): void
	{
		$name = (new SuiteSource())->className($methods, $dataSets);
		if (!class_exists($name, false)) {
			throw new LogicException(sprintf('declareClass() did not declare %s.', $name));
		}

		$class = new ReflectionClass($name);
		$hooks = $this->expander->hooks($class);
		foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->isStatic()) {
				continue;
			}

			$this->expander->expand($class, $method, $hooks, $this->configuration);
		}
	}

}
