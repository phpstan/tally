<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Complexity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Attribute\Complexity;
use Tally\Attribute\Constant;
use Tally\Attribute\Exponential;
use Tally\Attribute\Linear;
use Tally\Attribute\Linearithmic;
use Tally\Attribute\Logarithmic;
use Tally\Attribute\Polynomial;
use Tally\Attribute\Quadratic;
use Tally\Attribute\RelativeTolerance;
use Tally\Comparison\Comparator;
use Tally\Complexity\ComplexityClass;
use Tally\Complexity\PresetFit;
use Tally\Tests\Expressions;
use function array_map;

final class PresetFitTest extends TestCase
{

	private const SIZE = 'input.rows';

	/**
	 * Each preset in the order of complexity, with instructions at the sizes 10, 20, and 40 that grow
	 * exactly as it allows from 1000 at size 10, so that they fit it and no preset below it.
	 *
	 * @return list<array{ComplexityClass, non-empty-list<int>}>
	 */
	private static function presets(): array
	{
		return [
			[new Constant(self::SIZE), [1000, 1000, 1000]],
			// log(20) / log(10) is 1.301
			[new Logarithmic(self::SIZE), [1000, 1301, 1602]],
			[new Linear(self::SIZE), [1000, 2000, 4000]],
			// 20 log(20) / (10 log(10)) is 2.602
			[new Linearithmic(self::SIZE), [1000, 2602, 6408]],
			[new Quadratic(self::SIZE), [1000, 4000, 16000]],
			[new Polynomial(self::SIZE, 3), [1000, 8000, 64000]],
			[new Polynomial(self::SIZE, 4), [1000, 16000, 256000]],
			// 2^10 and 2^30 times, which no polynomial below degree 15 allows at size 40
			[new Exponential(self::SIZE), [1000, 1024000, 1073741824000]],
		];
	}

	/**
	 * @return iterable<string, array{ComplexityClass, non-empty-list<int>, ComplexityClass}>
	 */
	public static function dataNextClass(): iterable
	{
		$presets = self::presets();
		foreach ($presets as $i => [$lower, $lowerGrowth]) {
			$next = $presets[$i + 1] ?? null;
			if ($next === null) {
				break;
			}

			[$higher, $higherGrowth] = $next;
			yield $lower->describe() . ' up to ' . $higher->describe() => [$lower, $higherGrowth, $higher];
			yield $higher->describe() . ' down to ' . $lower->describe() => [$higher, $lowerGrowth, $lower];
		}
	}

	/**
	 * @param non-empty-list<int> $instructions at the sizes 10, 20, and 40
	 */
	#[DataProvider('dataNextClass')]
	public function testNextClass(ComplexityClass $current, array $instructions, ComplexityClass $fitted): void
	{
		// equal, not only described the same: #[Quadratic] and not #[Polynomial(degree: 2)]
		$this->assertEquals($fitted, self::fit($current, self::points([10, 20, 40], $instructions)));
	}

	/**
	 * @return iterable<string, array{ComplexityClass, non-empty-list<int>}>
	 */
	public static function dataKeepsTheClass(): iterable
	{
		yield 'the class that fits' => [new Linearithmic(self::SIZE), [1000, 2602, 6408]];
		yield 'a polynomial of degree 1 is linear' => [new Polynomial(self::SIZE, 1), [1000, 2000, 4000]];
		yield 'a polynomial of degree 2 is quadratic' => [new Polynomial(self::SIZE, 2), [1000, 4000, 16000]];
		yield 'not a preset' => [new Complexity(self::SIZE, 'n^3'), [1000, 2000, 4000]];
	}

	/**
	 * @param non-empty-list<int> $instructions at the sizes 10, 20, and 40
	 */
	#[DataProvider('dataKeepsTheClass')]
	public function testKeepsTheClass(ComplexityClass $current, array $instructions): void
	{
		$this->assertSame($current, self::fit($current, self::points([10, 20, 40], $instructions)));
	}

	public function testTheBaseIsTheSmallestSize(): void
	{
		$fit = self::fit(new Linear(self::SIZE), self::points([40, 10, 20], [16000, 1000, 4000]));

		$this->assertSame('O(n^2)', $fit?->describe());
	}

	public function testWithinTheTolerance(): void
	{
		// 9 % more than the formula allows at size 20
		$fit = self::fit(new Linear(self::SIZE), self::points([10, 20, 40], [1000, 2180, 4000]));

		$this->assertSame('O(n)', $fit?->describe());
		$this->assertSame('O(n log n)', self::fit(new Linear(self::SIZE), self::points([10, 20, 40], [1000, 2220, 4000]))?->describe());
	}

	public function testAClassThatIsNotDefinedAtEverySizeIsSkipped(): void
	{
		// log(1) is 0
		$this->assertEquals(new Linear(self::SIZE), self::fit(new Constant(self::SIZE), self::points([1, 2, 4], [1000, 2000, 4000])));
	}

	/**
	 * @return iterable<string, array{int, ComplexityClass}>
	 */
	public static function dataRoundsTheExpectedInstructions(): iterable
	{
		// 3 times 2.5 rounds up to 8, which 8 is within
		yield 'up' => [25, new Linear(self::SIZE)];
		// 3 times 2.4 rounds down to 7, which 8 is 14 % more than
		yield 'down' => [24, new Linearithmic(self::SIZE)];
	}

	#[DataProvider('dataRoundsTheExpectedInstructions')]
	public function testRoundsTheExpectedInstructionsLikeTallyRun(int $size, ComplexityClass $fitted): void
	{
		$this->assertEquals($fitted, self::fit(new Linear(self::SIZE), self::points([10, $size], [3, 8])));
	}

	public function testExponentialAllowsLessThanAPolynomialAtSmallSizes(): void
	{
		// n^3 allows 27 times at size 6, 2^n 16 times
		$fit = self::fit(new Polynomial(self::SIZE, 3), self::points([2, 4, 6], [1000, 4000, 16000]));

		$this->assertSame('O(2^n)', $fit?->describe());
	}

	public function testAPolynomialThatAllowsLessThanExponential(): void
	{
		// n^3 allows 27 times at size 6 and 2^n 16 times, which the instructions grew more than
		$fit = self::fit(new Exponential(self::SIZE), self::points([2, 4, 6], [1000, 8000, 27000]));

		$this->assertSame('O(n^3)', $fit?->describe());
	}

	public function testNoPresetFits(): void
	{
		// every formula but 1 and 2^n is 0 or undefined at size 0, and 2^n allows 4 times at size 2
		$this->assertNull(self::fit(new Linear(self::SIZE), self::points([0, 1, 2], [1000, 5000, 100000])));
	}

	/**
	 * @param non-empty-list<array{float, int}> $points
	 */
	private static function fit(ComplexityClass $current, array $points): ?ComplexityClass
	{
		$expressions = new Expressions();

		return (new PresetFit($expressions->parser(), $expressions->evaluator(), new Comparator()))->fit($current, $points, new RelativeTolerance(0.1));
	}

	/**
	 * @param non-empty-list<int> $sizes
	 * @param non-empty-list<int> $instructions
	 * @return non-empty-list<array{float, int}>
	 */
	private static function points(array $sizes, array $instructions): array
	{
		return array_map(static fn (int $size, int $count): array => [(float) $size, $count], $sizes, $instructions);
	}

}
