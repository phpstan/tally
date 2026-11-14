<?php declare(strict_types = 1);

namespace Tally\Bench\Expectation;

use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;
use Tally\Attribute\Warmup;
use Tally\Bench\SuiteSource;
use Tally\Expectation\AttributeIndex;
use Tally\Expectation\ExpectationException;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Expectation\MethodNotFoundException;
use Tally\Metrics;
use function sprintf;

/**
 * Every tally command indexes the attributes of each benchmark file once, and tally run, compare,
 * and accept then locate and rewrite the expectation of each case in the index.
 */
#[Warmup(1)]
final class ExpectedAttributeRewriterBench
{

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function files(): iterable
	{
		$source = new SuiteSource();
		foreach ([10, 100] as $size) {
			yield sprintf('size-%d', $size) => [$source->code($size, $size, true)];
		}
	}

	#[DataProvider('files')]
	#[ExpectedFor('size-10', instructions: '2.007M', cycles: '2.209M', memory: '135.9KiB')]
	#[ExpectedFor('size-100', instructions: '17.36M', cycles: '19.11M', memory: '1.093MiB')]
	public function benchIndex(string $code): void
	{
		(new ExpectedAttributeRewriter())->index($code);
	}

	/**
	 * @return iterable<string, array{AttributeIndex, string, string, Metrics}> the index, the class, the data set whose attribute to rewrite, and its metrics
	 */
	public static function indexes(): iterable
	{
		$source = new SuiteSource();
		foreach ([10, 100] as $size) {
			yield sprintf('size-%d', $size) => [
				(new ExpectedAttributeRewriter())->index($source->code($size, $size, true)),
				$source->className($size, $size),
				$source->dataSet($size),
				$source->measured(1),
			];
		}
	}

	/**
	 * @throws ExpectationException
	 * @throws MethodNotFoundException
	 */
	#[DataProvider('indexes')]
	#[ExpectedFor('size-10', instructions: '47.67K', cycles: '65.38K', memory: '1.445KiB')]
	#[ExpectedFor('size-100', instructions: '80.65K', cycles: '103.6K', memory: '1.445KiB')]
	public function benchEdits(AttributeIndex $index, string $class, string $dataSet, Metrics $measured): void
	{
		(new ExpectedAttributeRewriter())->edits($index, $class, SuiteSource::SIZED_METHOD, $dataSet, $measured, false);
	}

}
