<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Expectation;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Expectation\ExpectationException;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Expectation\MethodNotFoundException;
use Tally\Metrics;
use Tally\Tests\Fixtures\Rewriter\SortBench;
use function file_get_contents;
use function str_replace;
use function substr_count;

final class ExpectedAttributeRewriterTest extends TestCase
{

	private const SORT_BENCH = __DIR__ . '/../../Fixtures/Rewriter/SortBench.php';

	/**
	 * @throws ExpectationException
	 */
	public function testRewriteReplacesOnlyTheAttributeOfTheMethod(): void
	{
		$code = self::read(self::SORT_BENCH);

		$rewritten = (new ExpectedAttributeRewriter())->rewrite(
			$code,
			SortBench::class,
			'benchSort',
			null,
			Metrics::fromArray(['instructions' => 2000, 'cycles' => 2200, 'memory' => 1024]),
		);

		// OtherBench::benchSort has the same method name and keeps its attribute
		$this->assertSame(
			str_replace(
				"#[Expected(instructions: '1.000K', cycles: '1.100K', memory: '0B')]",
				"#[Expected(instructions: '2.000K', cycles: '2.200K', memory: '1.000KiB')]",
				$code,
			),
			$rewritten,
		);
	}

	/**
	 * @throws ExpectationException
	 */
	public function testRewriteReplacesTheAttributeOfTheDataSet(): void
	{
		$code = self::read(self::SORT_BENCH);

		$rewritten = (new ExpectedAttributeRewriter())->rewrite($code, SortBench::class, 'benchSizes', 'large', Metrics::fromArray(['instructions' => 20_000, 'cycles' => 22_000, 'memory' => 2048]));

		$this->assertSame(
			str_replace("#[ExpectedFor(dataSet: 'large', instructions: '10.00K', cycles: '12.00K', memory: '0B')]", "#[ExpectedFor('large', instructions: '20.00K', cycles: '22.00K', memory: '2.000KiB')]", $code),
			$rewritten,
		);
	}

	/**
	 * @throws ExpectationException
	 */
	public function testRewriteAddsAMissingAttributeAfterTheOthers(): void
	{
		$code = self::read(self::SORT_BENCH);

		$rewritten = (new ExpectedAttributeRewriter())->rewrite($code, SortBench::class, 'benchWithoutExpectations', null, Metrics::fromArray(['instructions' => 5, 'cycles' => 6, 'memory' => 0]));

		$this->assertSame(
			str_replace("\t#[Warmup(1)]\n", "\t#[Warmup(1)]\n\t#[Expected(instructions: '5', cycles: '6', memory: '0B')]\n", $code),
			$rewritten,
		);
	}

	/**
	 * @throws ExpectationException
	 */
	public function testRewriteAddsAMissingDataSetAttribute(): void
	{
		$code = self::read(self::SORT_BENCH);

		$rewritten = (new ExpectedAttributeRewriter())->rewrite($code, SortBench::class, 'benchSizes', "it's new", Metrics::fromArray(['instructions' => 1, 'cycles' => 2, 'memory' => 0]));

		$this->assertSame(
			str_replace(
				"\t#[ExpectedFor(dataSet: 'large', instructions: '10.00K', cycles: '12.00K', memory: '0B')]\n",
				"\t#[ExpectedFor(dataSet: 'large', instructions: '10.00K', cycles: '12.00K', memory: '0B')]\n\t#[ExpectedFor('it\\'s new', instructions: '1', cycles: '2', memory: '0B')]\n",
				$code,
			),
			$rewritten,
		);
	}

	/**
	 * @throws ExpectationException
	 */
	public function testRewriteImportsTheAttribute(): void
	{
		$code = self::read(__DIR__ . '/../../Fixtures/Rewriter/NoImportBench.php');

		$rewritten = (new ExpectedAttributeRewriter())->rewrite($code, 'Tally\\Tests\\Fixtures\\Rewriter\\NoImportBench', 'benchSprintf', null, Metrics::fromArray(['instructions' => 1, 'cycles' => 1, 'memory' => 0]));

		$this->assertSame(
			str_replace(
				["use function sprintf;\n", "\tpublic function benchSprintf"],
				["use Tally\\Attribute\\Expected;\nuse function sprintf;\n", "\t#[Expected(instructions: '1', cycles: '1', memory: '0B')]\n\tpublic function benchSprintf"],
				$code,
			),
			$rewritten,
		);
	}

	/**
	 * @throws ExpectationException
	 */
	public function testRewriteTwiceAddsTheImportOnce(): void
	{
		$rewriter = new ExpectedAttributeRewriter();
		$class = 'Tally\\Tests\\Fixtures\\Rewriter\\NoImportBench';
		$code = $rewriter->rewrite(self::read(__DIR__ . '/../../Fixtures/Rewriter/NoImportBench.php'), $class, 'benchSprintf', null, Metrics::fromArray(['instructions' => 1, 'cycles' => 1, 'memory' => 0]));
		$code = $rewriter->rewrite($code, $class, 'benchSprintf', null, Metrics::fromArray(['instructions' => 2, 'cycles' => 2, 'memory' => 0]));

		$this->assertSame(1, substr_count($code, 'use Tally\\Attribute\\Expected;'));
		$this->assertSame(1, substr_count($code, "#[Expected(instructions: '2', cycles: '2', memory: '0B')]"));
	}

	/**
	 * @return iterable<string, array{string, string, string}>
	 */
	public static function dataRewriteCode(): iterable
	{
		yield 'imported under an alias' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected as E;\nclass FooBench {\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected as E;\nclass FooBench {\n\t#[E(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'another class named Expected' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nuse Other\\Expected;\nclass FooBench {\n\t#[Expected]\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Other\\Expected;\nclass FooBench {\n\t#[Expected]\n\t#[\\Tally\\Attribute\\Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'import sorted before a later one' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nuse Alpha\\A;\nuse Zulu\\Z;\nclass FooBench {\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Alpha\\A;\nuse Tally\\Attribute\\Expected;\nuse Zulu\\Z;\nclass FooBench {\n\t#[Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'import after the last one' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nuse Alpha\\A;\nclass FooBench {\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Alpha\\A;\nuse Tally\\Attribute\\Expected;\nclass FooBench {\n\t#[Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'import after the last one, before the functions' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nuse Alpha\\A;\nuse function strlen;\nclass FooBench {\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Alpha\\A;\nuse Tally\\Attribute\\Expected;\nuse function strlen;\nclass FooBench {\n\t#[Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'import before the first function' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nuse function strlen;\nuse const PHP_EOL;\nclass FooBench {\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected;\nuse function strlen;\nuse const PHP_EOL;\nclass FooBench {\n\t#[Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'import with a leading backslash' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nuse \\Tally\\Attribute\\Expected;\nclass FooBench {\n\t#[Expected(instructions: '1')]\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse \\Tally\\Attribute\\Expected;\nclass FooBench {\n\t#[Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'unqualified in the namespace of the attribute' => [
			'Tally\\Attribute\\FooBench',
			"<?php\nnamespace Tally\\Attribute;\nclass FooBench {\n\t#[Expected(instructions: '1')]\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace Tally\\Attribute;\nclass FooBench {\n\t#[Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'first import after the namespace' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nclass FooBench {\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\n\nuse Tally\\Attribute\\Expected;\nclass FooBench {\n\t#[Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'global namespace' => [
			'FooBench',
			"<?php\nclass FooBench {\n\t#[Warmup(1)]\n\tpublic function bench() {}\n}\n",
			"<?php\nclass FooBench {\n\t#[Warmup(1)]\n\t#[\\Tally\\Attribute\\Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'fully qualified attribute' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nclass FooBench {\n\t#[\\Tally\\Attribute\\Expected(instructions: '1')]\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nclass FooBench {\n\t#[\\Tally\\Attribute\\Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'qualified by an imported namespace' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nuse Tally\\Attribute;\nclass FooBench {\n\t#[Attribute\\Expected(instructions: '1')]\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Tally\\Attribute;\nclass FooBench {\n\t#[Attribute\\Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'group import' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nuse Tally\\Attribute\\{Warmup as W, Expected};\nclass FooBench {\n\t#[W(1), Expected(instructions: '1'),]\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Tally\\Attribute\\{Warmup as W, Expected};\nclass FooBench {\n\t#[W(1), Expected(instructions: '7', cycles: '8', memory: '0B'),]\n\tpublic function bench() {}\n}\n",
		];
		yield 'group without spaces' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected;\nuse Tally\\Attribute\\Warmup;\nclass FooBench {\n\t#[Warmup(1),Expected(instructions: '1')]\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected;\nuse Tally\\Attribute\\Warmup;\nclass FooBench {\n\t#[Warmup(1),Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'qualified by an aliased root namespace' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nuse Tally as T;\nclass FooBench {\n\t#[T\\Attribute\\Expected(instructions: '1')]\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Tally as T;\nclass FooBench {\n\t#[T\\Attribute\\Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'attribute without arguments' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected;\nclass FooBench {\n\t#[Expected]\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected;\nclass FooBench {\n\t#[Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'after a property and its attribute' => [
			'App\\FooBench',
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected;\nclass FooBench {\n\t#[Expected(instructions: '1')]\n\tpublic int \$size = 1;\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected;\nclass FooBench {\n\t#[Expected(instructions: '1')]\n\tpublic int \$size = 1;\n\t#[Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
	}

	/**
	 * @throws ExpectationException
	 */
	#[DataProvider('dataRewriteCode')]
	public function testRewriteCode(string $class, string $code, string $expected): void
	{
		$this->assertSame($expected, (new ExpectedAttributeRewriter())->rewrite($code, $class, 'bench', null, Metrics::fromArray(['instructions' => 7, 'cycles' => 8, 'memory' => 0])));
	}

	/**
	 * @return iterable<string, array{string, int}>
	 */
	public static function dataLocate(): iterable
	{
		yield 'named, after another argument' => ['a', 5];
		yield 'positional, escapes in single quotes are kept, except quotes and backslashes' => ["it's\\n\\", 6];
		yield 'escapes in double quotes are decoded' => ["tab\there", 7];
		yield 'where it would go' => ['missing', 8];
	}

	/**
	 * @throws ExpectationException
	 */
	#[DataProvider('dataLocate')]
	public function testLocateDataSet(string $dataSet, int $expected): void
	{
		$code = "<?php\nnamespace App;\nuse Tally\\Attribute\\ExpectedFor;\nclass FooBench {\n"
			. "\t#[ExpectedFor(instructions: '1', dataSet: 'a', cycles: '1', memory: '0B')]\n"
			. "\t#[ExpectedFor('it\\'s\\n\\\\', instructions: '1', cycles: '1', memory: '0B')]\n"
			. "\t#[ExpectedFor(\"tab\\there\", instructions: '1', cycles: '1', memory: '0B')]\n"
			. "\tpublic function bench() {}\n}\n";

		$this->assertSame($expected, (new ExpectedAttributeRewriter())->locate($code, 'App\\FooBench', 'bench', $dataSet));
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function dataNonLiteralDataSet(): iterable
	{
		yield 'a named constant' => ["#[ExpectedFor(dataSet: self::LARGE, instructions: '1', cycles: '1', memory: '0B')]"];
		yield 'a positional constant' => ["#[ExpectedFor(self::LARGE, instructions: '1', cycles: '1', memory: '0B')]"];
		yield 'a concatenation' => ["#[ExpectedFor('lar' . 'ge', instructions: '1', cycles: '1', memory: '0B')]"];
		yield 'a named concatenation' => ["#[ExpectedFor(dataSet: 'lar' . 'ge', instructions: '1', cycles: '1', memory: '0B')]"];
		yield 'no arguments' => ['#[ExpectedFor]'];
		yield 'empty arguments' => ['#[ExpectedFor()]'];
	}

	/**
	 * @throws ExpectationException
	 */
	#[DataProvider('dataNonLiteralDataSet')]
	public function testRewriteRejectsANonLiteralDataSet(string $attribute): void
	{
		$code = "<?php\nnamespace App;\nuse Tally\\Attribute\\ExpectedFor;\nclass FooBench {\n\t" . $attribute . "\n\tpublic function bench() {}\n}\n";

		$this->expectException(ExpectationException::class);
		$this->expectExceptionMessage('#[ExpectedFor] on line 5: write the data set as a string literal, so tally accept can find the attribute.');
		(new ExpectedAttributeRewriter())->rewrite($code, 'App\\FooBench', 'bench', 'large', Metrics::fromArray(['instructions' => 7, 'cycles' => 8, 'memory' => 0]));
	}

	/**
	 * @throws ExpectationException
	 */
	public function testANonLiteralDataSetOfAnotherMethodDoesNotMatter(): void
	{
		$code = "<?php\nnamespace App;\nuse Tally\\Attribute\\Expected;\nuse Tally\\Attribute\\ExpectedFor;\nclass FooBench {\n\t#[ExpectedFor(self::LARGE, '1', '1', '0B')]\n\tpublic function benchOther() {}\n\tpublic function bench() {}\n}\n";

		$this->assertSame(
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected;\nuse Tally\\Attribute\\ExpectedFor;\nclass FooBench {\n\t#[ExpectedFor(self::LARGE, '1', '1', '0B')]\n\tpublic function benchOther() {}\n\t#[Expected(instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
			(new ExpectedAttributeRewriter())->rewrite($code, 'App\\FooBench', 'bench', null, Metrics::fromArray(['instructions' => 7, 'cycles' => 8, 'memory' => 0])),
		);
		$this->assertSame(8, (new ExpectedAttributeRewriter())->locate($code, 'App\\FooBench', 'bench', null));
	}

	/**
	 * @return iterable<string, array{array<string, int>, string}>
	 */
	public static function dataIncompleteMetrics(): iterable
	{
		yield 'no instructions' => [['cycles' => 1, 'memory' => 0], 'The instructions to write are missing.'];
		yield 'no cycles' => [['instructions' => 1, 'memory' => 0], 'The cycles to write are missing.'];
		yield 'no memory' => [['instructions' => 1, 'cycles' => 1], 'The memory to write are missing.'];
	}

	/**
	 * @param array<string, int> $metrics
	 * @throws ExpectationException
	 */
	#[DataProvider('dataIncompleteMetrics')]
	public function testRewriteNeedsEveryMetric(array $metrics, string $message): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		(new ExpectedAttributeRewriter())->rewrite(self::read(self::SORT_BENCH), SortBench::class, 'benchSort', null, Metrics::fromArray($metrics));
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function dataRewriteDataSetCode(): iterable
	{
		yield 'imports ExpectedFor next to Expected' => [
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected;\nclass FooBench {\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected;\nuse Tally\\Attribute\\ExpectedFor;\nclass FooBench {\n\t#[ExpectedFor('a', instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'another data set is left alone' => [
			"<?php\nnamespace App;\nuse Tally\\Attribute\\ExpectedFor;\nclass FooBench {\n\t#[ExpectedFor('b', instructions: '1', cycles: '1', memory: '0B')]\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Tally\\Attribute\\ExpectedFor;\nclass FooBench {\n\t#[ExpectedFor('b', instructions: '1', cycles: '1', memory: '0B')]\n\t#[ExpectedFor('a', instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'an #[Expected] is not the data set' => [
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected;\nuse Tally\\Attribute\\ExpectedFor;\nclass FooBench {\n\t#[Expected(instructions: '1', cycles: '1', memory: '0B')]\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Tally\\Attribute\\Expected;\nuse Tally\\Attribute\\ExpectedFor;\nclass FooBench {\n\t#[Expected(instructions: '1', cycles: '1', memory: '0B')]\n\t#[ExpectedFor('a', instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
		yield 'another class named ExpectedFor' => [
			"<?php\nnamespace App;\nuse Other\\ExpectedFor;\nclass FooBench {\n\tpublic function bench() {}\n}\n",
			"<?php\nnamespace App;\nuse Other\\ExpectedFor;\nclass FooBench {\n\t#[\\Tally\\Attribute\\ExpectedFor('a', instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
		];
	}

	/**
	 * @throws ExpectationException
	 */
	#[DataProvider('dataRewriteDataSetCode')]
	public function testRewriteDataSetCode(string $code, string $expected): void
	{
		$this->assertSame($expected, (new ExpectedAttributeRewriter())->rewrite($code, 'App\\FooBench', 'bench', 'a', Metrics::fromArray(['instructions' => 7, 'cycles' => 8, 'memory' => 0])));
	}

	/**
	 * @throws ExpectationException
	 */
	public function testRewriteDataSetInTheGlobalNamespace(): void
	{
		$this->assertSame(
			"<?php\nclass FooBench {\n\t#[\\Tally\\Attribute\\ExpectedFor('a', instructions: '7', cycles: '8', memory: '0B')]\n\tpublic function bench() {}\n}\n",
			(new ExpectedAttributeRewriter())->rewrite("<?php\nclass FooBench {\n\tpublic function bench() {}\n}\n", 'FooBench', 'bench', 'a', Metrics::fromArray(['instructions' => 7, 'cycles' => 8, 'memory' => 0])),
		);
	}

	/**
	 * @throws ExpectationException
	 */
	public function testLocate(): void
	{
		$code = self::read(self::SORT_BENCH);
		$rewriter = new ExpectedAttributeRewriter();

		$this->assertSame(13, $rewriter->locate($code, SortBench::class, 'benchSort', null));
		$this->assertSame(25, $rewriter->locate($code, SortBench::class, 'benchSizes', 'large'));
		$this->assertSame(24, $rewriter->locate($code, SortBench::class, 'benchSizes', 'small'));
		$this->assertSame(35, $rewriter->locate($code, 'Tally\\Tests\\Fixtures\\Rewriter\\OtherBench', 'benchSort', null));
		// where the attribute would go
		$this->assertSame(19, $rewriter->locate($code, SortBench::class, 'benchWithoutExpectations', null));
	}

	/**
	 * @throws ExpectationException
	 */
	public function testLocateFailsForAMissingMethod(): void
	{
		$this->expectException(MethodNotFoundException::class);
		$this->expectExceptionMessage('Tally\Tests\Fixtures\Rewriter\SortBench::benchMissing');
		(new ExpectedAttributeRewriter())->locate(self::read(self::SORT_BENCH), SortBench::class, 'benchMissing', null);
	}

	private static function read(string $file): string
	{
		$code = file_get_contents($file);
		self::assertIsString($code);

		return $code;
	}

}
