<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Expectation;

use PHPUnit\Framework\TestCase;
use Tally\Attribute\RelativeTolerance;
use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Expectation\AttributeExpectations;
use Tally\Expectation\CodeEdit;
use Tally\Expectation\ExpectationChanges;
use Tally\Expectation\ExpectationSource;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Expectation\FileChange;
use Tally\Metrics;
use Tally\Tests\FixedEdits;
use Tally\Tests\InMemoryExpectations;
use function array_map;
use function str_replace;

final class ExpectationChangesTest extends TestCase
{

	public function testCombinesTheChangesOfAFileInTheOrderOfTheCases(): void
	{
		$plan = (new ExpectationChanges())->plan(
			[
				[self::case('A::benchA', new InMemoryExpectations(null, '/a.php')), Metrics::fromArray(['instructions' => 1])],
				[self::case('B::benchB', new InMemoryExpectations(null, '/b.php')), Metrics::fromArray(['instructions' => 2])],
				[self::case('A::benchC', new InMemoryExpectations(null, '/a.php')), Metrics::fromArray(['instructions' => 3])],
			],
			['/a.php' => "<?php\n", '/b.php' => "<?php // b\n", '/unused.php' => ''],
		);

		$this->assertSame(
			[
				['/a.php', "<?php\n", "<?php\n{\"instructions\":1}\n{\"instructions\":3}\n", ['A::benchA', 'A::benchC']],
				['/b.php', "<?php // b\n", "<?php // b\n{\"instructions\":2}\n", ['B::benchB']],
			],
			self::files($plan->files),
		);
		$this->assertSame([], $plan->failures);
	}

	public function testACaseThatCannotBeRewrittenDoesNotStopTheOthersInItsFile(): void
	{
		$code = "<?php\n\nnamespace App;\n\nuse Tally\\Attribute\\ExpectedFor;\n\nfinal class SortBench\n{\n\n\t#[ExpectedFor(self::LARGE, '1', '1', '0B')]\n\tpublic function benchLarge(): void\n\t{\n\t}\n\n\tpublic function benchSort(): void\n\t{\n\t}\n\n}\n";
		$rewriter = new ExpectedAttributeRewriter();
		$index = $rewriter->index($code);

		$plan = (new ExpectationChanges())->plan(
			[
				[self::case('App\GoneBench::benchGone', new InMemoryExpectations(null, '/GoneBench.php')), Metrics::fromArray(['instructions' => 3])],
				[self::case('App\SortBench::benchLarge@large', new AttributeExpectations($rewriter, '/SortBench.php', $index, 'App\SortBench', 'benchLarge', 'large', null, false)), Metrics::fromArray(['instructions' => 1, 'cycles' => 1, 'memory' => 0])],
				[self::case('App\SortBench::benchSort', new AttributeExpectations($rewriter, '/SortBench.php', $index, 'App\SortBench', 'benchSort', null, null, false)), Metrics::fromArray(['instructions' => 2, 'cycles' => 2, 'memory' => 0])],
			],
			['/SortBench.php' => $code, '/GoneBench.php' => false],
		);

		$this->assertSame([['App\SortBench::benchSort']], array_map(static fn (FileChange $file): array => $file->cases, $plan->files));
		$this->assertSame(
			[str_replace(
				['use Tally\\Attribute\\ExpectedFor;', "\tpublic function benchSort()"],
				["use Tally\\Attribute\\Expected;\nuse Tally\\Attribute\\ExpectedFor;", "\t#[Expected(instructions: '2', cycles: '2', memory: '0B')]\n\tpublic function benchSort()"],
				$code,
			)],
			array_map(static fn (FileChange $file): string => $file->after, $plan->files),
		);
		$this->assertSame(
			[
				'App\GoneBench::benchGone' => 'Cannot read /GoneBench.php.',
				'App\SortBench::benchLarge@large' => '#[ExpectedFor] on line 10: write the data set as a string literal, so tally accept can find the attribute.',
			],
			$plan->failures,
		);
	}

	public function testCasesThatAddTheSameImportAddItOnceAndKeepTheImportsSorted(): void
	{
		$code = "<?php\nnamespace App;\nclass SortBench {\n\tpublic function benchA() {}\n\tpublic function benchB() {}\n\tpublic function benchC() {}\n}\n";
		$rewriter = new ExpectedAttributeRewriter();
		$index = $rewriter->index($code);
		$metrics = Metrics::fromArray(['instructions' => 1, 'cycles' => 2, 'memory' => 0]);

		$plan = (new ExpectationChanges())->plan(
			[
				[self::case('App\SortBench::benchA@x', new AttributeExpectations($rewriter, '/SortBench.php', $index, 'App\SortBench', 'benchA', 'x', null, false)), $metrics],
				[self::case('App\SortBench::benchB', new AttributeExpectations($rewriter, '/SortBench.php', $index, 'App\SortBench', 'benchB', null, null, false)), $metrics],
				[self::case('App\SortBench::benchC', new AttributeExpectations($rewriter, '/SortBench.php', $index, 'App\SortBench', 'benchC', null, null, false)), $metrics],
			],
			['/SortBench.php' => $code],
		);

		$this->assertSame(
			[[
				'/SortBench.php',
				$code,
				"<?php\nnamespace App;\n\nuse Tally\\Attribute\\Expected;\nuse Tally\\Attribute\\ExpectedFor;\nclass SortBench {\n"
				. "\t#[ExpectedFor('x', instructions: '1', cycles: '2', memory: '0B')]\n\tpublic function benchA() {}\n"
				. "\t#[Expected(instructions: '1', cycles: '2', memory: '0B')]\n\tpublic function benchB() {}\n"
				. "\t#[Expected(instructions: '1', cycles: '2', memory: '0B')]\n\tpublic function benchC() {}\n}\n",
				['App\SortBench::benchA@x', 'App\SortBench::benchB', 'App\SortBench::benchC'],
			]],
			self::files($plan->files),
		);
		$this->assertSame([], $plan->failures);
	}

	public function testEditsAtOneOffsetGoInTheNaturalOrderOfTheirTextAndInsertionsBeforeReplacements(): void
	{
		$plan = (new ExpectationChanges())->plan(
			[
				[self::case('A::bench10', new FixedEdits('/a.php', [new CodeEdit(1, 2, '[10]')])), Metrics::none()],
				[self::case('A::bench2', new FixedEdits('/a.php', [new CodeEdit(1, 0, '<2>')])), Metrics::none()],
				[self::case('A::bench1', new FixedEdits('/a.php', [new CodeEdit(1, 0, '<10>'), new CodeEdit(3, 0, '<3>')])), Metrics::none()],
			],
			['/a.php' => 'abcd'],
		);

		$this->assertSame([['/a.php', 'abcd', 'a<2><10>[10]<3>d', ['A::bench10', 'A::bench2', 'A::bench1']]], self::files($plan->files));
		$this->assertSame([], $plan->failures);
	}

	public function testACaseWhoseEditsOverlapAnotherCaseIsLeftOut(): void
	{
		$plan = (new ExpectationChanges())->plan(
			[
				[self::case('A::benchA', new FixedEdits('/a.php', [new CodeEdit(1, 2, 'X')])), Metrics::none()],
				[self::case('A::benchB', new FixedEdits('/a.php', [new CodeEdit(0, 1, 'Y'), new CodeEdit(2, 0, 'Z')])), Metrics::none()],
				[self::case('A::benchC', new FixedEdits('/a.php', [new CodeEdit(1, 2, 'X'), new CodeEdit(3, 1, 'W')])), Metrics::none()],
				[self::case('A::benchD', new FixedEdits('/a.php', [new CodeEdit(2, 1, 'V')])), Metrics::none()],
			],
			['/a.php' => 'abcd'],
		);

		$this->assertSame([['/a.php', 'abcd', 'aXW', ['A::benchA', 'A::benchC']]], self::files($plan->files));
		$this->assertSame(['A::benchB' => 'Its expectations overlap those of A::benchA.', 'A::benchD' => 'Its expectations overlap those of A::benchA.'], $plan->failures);
	}

	public function testAFileThatWasNotReadCannotBeRewritten(): void
	{
		$plan = (new ExpectationChanges())->plan([[self::case('A::bench', new InMemoryExpectations(null, '/a.php')), Metrics::none()]], []);

		$this->assertSame([], $plan->files);
		$this->assertSame(['A::bench' => 'Cannot read /a.php.'], $plan->failures);
	}

	public function testNothingToChange(): void
	{
		$plan = (new ExpectationChanges())->plan([], ['/a.php' => '']);

		$this->assertSame([], $plan->files);
		$this->assertSame([], $plan->failures);
	}

	/**
	 * @param list<FileChange> $files
	 * @return list<array{string, string, string, list<string>}>
	 */
	private static function files(array $files): array
	{
		return array_map(static fn (FileChange $file): array => [$file->file, $file->before, $file->after, $file->cases], $files);
	}

	private static function case(string $id, ExpectationSource $expectations): BenchmarkCase
	{
		return new BenchmarkCase(CaseId::fromString($id), '/benchmarks/FooBench.php', [], 0, [], [], $expectations, new RelativeTolerance(0.1), null);
	}

}
