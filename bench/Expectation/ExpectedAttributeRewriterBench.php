<?php declare(strict_types = 1);

namespace Tally\Bench\Expectation;

use Tally\Attribute\DataProvider;
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
	public function benchRewrite(string $code, string $class, string $dataSet, Metrics $measured): void
	{
		(new ExpectedAttributeRewriter())->rewrite($code, $class, SuiteSource::SIZED_METHOD, $dataSet, $measured);
	}

}
