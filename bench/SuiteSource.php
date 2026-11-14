<?php declare(strict_types = 1);

namespace Tally\Bench;

use Tally\Attribute\RelativeTolerance;
use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Expectation\AttributeExpectations;
use Tally\Expectation\AttributeIndex;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Metric;
use Tally\Metrics;
use function sprintf;

/**
 * Generates the code of a benchmark class as large as those of a big suite: methods with
 * #[Expected], and one method with a data provider and #[ExpectedFor] for each data set.
 */
final class SuiteSource
{

	public const NAMESPACE = 'Tally\Bench\Generated';

	public const SIZED_METHOD = 'benchSized';

	public function className(int $methods, int $dataSets): string
	{
		return sprintf('%s\Suite%dx%dBench', self::NAMESPACE, $methods, $dataSets);
	}

	public const FILE = '/project/bench/SuiteBench.php';

	/**
	 * The cases of the class code() generates, before they have expectations, with the metrics
	 * tally run measured for them.
	 *
	 * @return list<array{BenchmarkCase, Metrics}>
	 */
	public function measuredCases(int $methods, int $dataSets): array
	{
		$rewriter = new ExpectedAttributeRewriter();
		$index = $rewriter->index($this->code($methods, $dataSets, false));
		$class = $this->className($methods, $dataSets);
		$cases = [];
		for ($i = 1; $i <= $methods; $i++) {
			$cases[] = [$this->case($rewriter, $index, $class, sprintf('benchMethod%d', $i), null), $this->measured($i)];
		}
		for ($i = 1; $i <= $dataSets; $i++) {
			$cases[] = [$this->case($rewriter, $index, $class, self::SIZED_METHOD, $this->dataSet($i)), $this->measured($i)];
		}

		return $cases;
	}

	/** Metrics as tally run measures them for a case */
	public function measured(int $index): Metrics
	{
		return Metrics::none()
			->with(Metric::Instructions, 1_234_567 * $index)
			->with(Metric::Cycles, 2_345_678 * $index)
			->with(Metric::Memory, 1_024 * $index);
	}

	private function case(ExpectedAttributeRewriter $rewriter, AttributeIndex $index, string $class, string $method, ?string $dataSet): BenchmarkCase
	{
		return new BenchmarkCase(
			new CaseId($class, $method, $dataSet),
			self::FILE,
			[],
			0,
			[],
			[],
			new AttributeExpectations($rewriter, self::FILE, $index, $class, $method, $dataSet, null, false),
			new RelativeTolerance(0.1),
			null,
		);
	}

	public function dataSet(int $index): string
	{
		return sprintf('size-%d', $index);
	}

	/**
	 * @param bool $expected whether the methods and data sets have their expectations yet
	 */
	public function code(int $methods, int $dataSets, bool $expected): string
	{
		$code = sprintf(
			"<?php declare(strict_types = 1);\n\nnamespace %s;\n\nuse Tally\\Attribute\\DataProvider;\n%s\nfinal class Suite%dx%dBench\n{\n\n",
			self::NAMESPACE,
			$expected ? "use Tally\\Attribute\\Expected;\nuse Tally\\Attribute\\ExpectedFor;\n" : '',
			$methods,
			$dataSets,
		);
		for ($i = 1; $i <= $methods; $i++) {
			if ($expected) {
				$code .= sprintf("\t#[Expected(instructions: '%d.%03dM', cycles: '%d.%03dM', memory: '%d.00KiB')]\n", $i, $i, 2 * $i, $i, $i);
			}
			$code .= sprintf("\tpublic function benchMethod%d(): void\n\t{\n\t\tstr_repeat('x', %d);\n\t}\n\n", $i, $i);
		}

		$code .= sprintf("\t/** @return iterable<string, array{int}> */\n\tpublic static function sizes(): iterable\n\t{\n\t\tfor (\$i = 1; \$i <= %d; \$i++) {\n\t\t\tyield 'size-' . \$i => [\$i];\n\t\t}\n\t}\n\n", $dataSets);
		$code .= "\t#[DataProvider('sizes')]\n";
		for ($i = 1; $i <= $dataSets && $expected; $i++) {
			$code .= sprintf("\t#[ExpectedFor('%s', instructions: '%d.%03dK', cycles: '%d.%03dK', memory: '0B')]\n", $this->dataSet($i), $i, $i, 2 * $i, $i);
		}

		return $code . sprintf("\tpublic function %s(int \$size): void\n\t{\n\t\tstr_repeat('x', \$size);\n\t}\n\n}\n", self::SIZED_METHOD);
	}

}
