<?php declare(strict_types = 1);

namespace Tally\Expectation;

use PHPUnit\Framework\TestCase;
use Tally\Expectation\data\OtherBench;
use Tally\Expectation\data\SortBench;
use Tally\Metrics;
use function file_get_contents;
use function str_replace;
use function substr_count;

class ExpectedAttributeRewriterTest extends TestCase
{

	private const SORT_BENCH = __DIR__ . '/data/SortBench.php';

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

	public function testRewriteReplacesTheAttributeOfTheDataSet(): void
	{
		$code = self::read(self::SORT_BENCH);

		$rewritten = (new ExpectedAttributeRewriter())->rewrite($code, SortBench::class, 'benchSizes', 'large', Metrics::fromArray(['instructions' => 20_000]));

		$this->assertSame(
			str_replace("#[Expected(dataSet: 'large', instructions: '10.00K')]", "#[Expected(dataSet: 'large', instructions: '20.00K')]", $code),
			$rewritten,
		);
	}

	public function testRewriteAddsAMissingAttributeAfterTheOthers(): void
	{
		$code = self::read(self::SORT_BENCH);

		$rewritten = (new ExpectedAttributeRewriter())->rewrite($code, SortBench::class, 'benchWithoutExpectations', null, Metrics::fromArray(['instructions' => 5]));

		$this->assertSame(
			str_replace("\t#[Warmup(1)]\n", "\t#[Warmup(1)]\n\t#[Expected(instructions: '5')]\n", $code),
			$rewritten,
		);
	}

	public function testRewriteAddsAMissingDataSetAttribute(): void
	{
		$code = self::read(self::SORT_BENCH);

		$rewritten = (new ExpectedAttributeRewriter())->rewrite($code, SortBench::class, 'benchSizes', "it's new", Metrics::fromArray(['memory' => 0]));

		$this->assertSame(
			str_replace(
				"\t#[Expected(dataSet: 'large', instructions: '10.00K')]\n",
				"\t#[Expected(dataSet: 'large', instructions: '10.00K')]\n\t#[Expected(dataSet: 'it\\'s new', memory: '0B')]\n",
				$code,
			),
			$rewritten,
		);
	}

	public function testRewriteImportsTheAttribute(): void
	{
		$code = self::read(__DIR__ . '/data/NoImportBench.php');

		$rewritten = (new ExpectedAttributeRewriter())->rewrite($code, 'Tally\\Expectation\\data\\NoImportBench', 'benchSprintf', null, Metrics::fromArray(['instructions' => 1]));

		$this->assertSame(
			str_replace(
				["use function sprintf;\n", "\tpublic function benchSprintf"],
				["use Tally\\Attribute\\Expected;\nuse function sprintf;\n", "\t#[Expected(instructions: '1')]\n\tpublic function benchSprintf"],
				$code,
			),
			$rewritten,
		);
	}

	public function testRewriteTwiceAddsTheImportOnce(): void
	{
		$rewriter = new ExpectedAttributeRewriter();
		$class = 'Tally\\Expectation\\data\\NoImportBench';
		$code = $rewriter->rewrite(self::read(__DIR__ . '/data/NoImportBench.php'), $class, 'benchSprintf', null, Metrics::fromArray(['instructions' => 1]));
		$code = $rewriter->rewrite($code, $class, 'benchSprintf', null, Metrics::fromArray(['instructions' => 2]));

		$this->assertSame(1, substr_count($code, 'use Tally\\Attribute\\Expected;'));
		$this->assertSame(1, substr_count($code, "#[Expected(instructions: '2')]"));
	}

	public function testLocate(): void
	{
		$code = self::read(self::SORT_BENCH);
		$rewriter = new ExpectedAttributeRewriter();

		$this->assertSame(12, $rewriter->locate($code, SortBench::class, 'benchSort', null));
		$this->assertSame(24, $rewriter->locate($code, SortBench::class, 'benchSizes', 'large'));
		$this->assertSame(34, $rewriter->locate($code, OtherBench::class, 'benchSort', null));
		// where the attribute would go
		$this->assertSame(18, $rewriter->locate($code, SortBench::class, 'benchWithoutExpectations', null));
	}

	public function testLocateFailsForAMissingMethod(): void
	{
		$this->expectException(MethodNotFoundException::class);
		$this->expectExceptionMessage('Tally\Expectation\data\SortBench::benchMissing');
		(new ExpectedAttributeRewriter())->locate(self::read(self::SORT_BENCH), SortBench::class, 'benchMissing', null);
	}

	private static function read(string $file): string
	{
		$code = file_get_contents($file);
		self::assertIsString($code);

		return $code;
	}

}
