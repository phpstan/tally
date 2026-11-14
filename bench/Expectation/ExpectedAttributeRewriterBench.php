<?php declare(strict_types = 1);

namespace Tally\Bench\Expectation;

use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;
use Tally\Attribute\Warmup;
use Tally\Bench\SuiteSource;
use Tally\Expectation\ExpectationException;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Expectation\MethodNotFoundException;
use Tally\Metrics;
use function sprintf;

/**
 * tally run and tally compare locate the expectation of every case in its file, and tally accept
 * rewrites it, so a file with many cases is tokenized once per case.
 */
#[Warmup(1)]
final class ExpectedAttributeRewriterBench
{

	/**
	 * @return iterable<string, array{string, string, string}> the code, the class, and the data set whose attribute to find
	 */
	public static function files(): iterable
	{
		$source = new SuiteSource();
		foreach ([10, 100] as $size) {
			yield sprintf('size-%d', $size) => [
				$source->code($size, $size, true),
				$source->className($size, $size),
				$source->dataSet($size),
			];
		}
	}

	/**
	 * @throws ExpectationException
	 * @throws MethodNotFoundException
	 */
	#[DataProvider('files')]
	#[ExpectedFor('size-10', instructions: '1.906M', cycles: '2.077M', memory: '128.6KiB')]
	#[ExpectedFor('size-100', instructions: '16.84M', cycles: '18.60M', memory: '1.002MiB')]
	public function benchLocate(string $code, string $class, string $dataSet): void
	{
		(new ExpectedAttributeRewriter())->locate($code, $class, SuiteSource::SIZED_METHOD, $dataSet);
	}

	/**
	 * @return iterable<string, array{string, string, string, Metrics}> files() with the measured metrics
	 */
	public static function rewrites(): iterable
	{
		foreach (self::files() as $name => $arguments) {
			yield $name => [...$arguments, (new SuiteSource())->measured(1)];
		}
	}

	/**
	 * @throws ExpectationException
	 * @throws MethodNotFoundException
	 */
	#[DataProvider('rewrites')]
	#[ExpectedFor('size-10', instructions: '1.947M', cycles: '2.133M', memory: '128.7KiB')]
	#[ExpectedFor('size-100', instructions: '16.91M', cycles: '18.68M', memory: '1.007MiB')]
	public function benchRewrite(string $code, string $class, string $dataSet, Metrics $measured): void
	{
		(new ExpectedAttributeRewriter())->rewrite($code, $class, SuiteSource::SIZED_METHOD, $dataSet, $measured, false);
	}

}
