<?php declare(strict_types = 1);

namespace Tally\Complexity;

use Generator;
use Tally\Attribute\Constant;
use Tally\Attribute\Exponential;
use Tally\Attribute\Linear;
use Tally\Attribute\Linearithmic;
use Tally\Attribute\Logarithmic;
use Tally\Attribute\Polynomial;
use Tally\Attribute\Quadratic;
use Tally\Comparison\Comparator;
use Tally\Comparison\Status;
use Tally\Comparison\TolerancePolicy;
use Tally\Expression\Evaluator;
use Tally\Expression\ExpressionException;
use Tally\Expression\Parser;
use Tally\Metric;
use function array_column;
use function array_map;
use function in_array;
use function is_finite;
use function max;
use function round;
use function usort;

/**
 * Finds the preset complexity class of a benchmark from its measured instructions, for tally
 * accept: the lowest in the order of complexity that the instructions grow within, as tally run
 * compares them. #[Exponential] comes before a class that allows more growth at the largest data
 * set, which every polynomial of a high enough degree does: any growth fits some degree at the
 * measured sizes.
 */
final class PresetFit
{

	private const PRESETS = [Constant::class, Logarithmic::class, Linear::class, Linearithmic::class, Quadratic::class, Polynomial::class, Exponential::class];

	public function __construct(private Parser $parser, private Evaluator $evaluator, private Comparator $comparator)
	{
	}

	/**
	 * @param ComplexityClass $current whose size the fitted class keeps, and which it stays when the fitted one grows the same, like #[Polynomial(degree: 1)] and #[Linear]
	 * @param non-empty-list<array{float, int}> $points the size and the measured instructions of each data set
	 * @return ComplexityClass|null the current class when it is not a preset, null when no preset fits
	 */
	public function fit(ComplexityClass $current, array $points, TolerancePolicy $tolerance): ?ComplexityClass
	{
		if (!in_array($current::class, self::PRESETS, true)) {
			return $current;
		}

		usort($points, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
		$size = $current->size();
		$exponential = new Exponential($size);
		$exponentialGrowth = $this->growth($exponential, $points);
		$exponentialFits = $exponentialGrowth !== null && $this->fits($exponentialGrowth, $tolerance);
		for ($presets = $this->presets($size); true; $presets->next()) {
			$preset = $presets->current();
			$growth = $this->growth($preset, $points);
			// like log(n) at size 1; a polynomial is not defined at the higher degrees either
			if ($growth === null && !$preset instanceof Polynomial) {
				continue;
			}
			if ($growth === null || $exponentialFits && $this->largest($growth) >= $this->largest($exponentialGrowth)) {
				return $this->kept($current, $exponentialFits ? $exponential : null, $points);
			}
			if ($this->fits($growth, $tolerance)) {
				return $this->kept($current, $preset, $points);
			}
		}
	}

	/**
	 * The presets below #[Exponential] in the order of complexity, the polynomials without end.
	 * #[Linear] and #[Quadratic] are the polynomials of degree 1 and 2.
	 *
	 * @return Generator<int, ComplexityClass>
	 */
	private function presets(string $size): Generator
	{
		yield new Constant($size);
		yield new Logarithmic($size);
		for ($degree = 1;; $degree++) {
			yield from match ($degree) {
				1 => [new Linear($size), new Linearithmic($size)],
				2 => [new Quadratic($size)],
				default => [new Polynomial($size, $degree)],
			};
		}
	}

	/**
	 * @param non-empty-list<array{float, int}> $points sorted by size
	 */
	private function kept(ComplexityClass $current, ?ComplexityClass $fitted, array $points): ?ComplexityClass
	{
		return $fitted !== null && $this->growth($fitted, $points) === $this->growth($current, $points) ? $current : $fitted;
	}

	/**
	 * How many times the formula is larger at each size than at the smallest one, as discovery
	 * computes it, with the measured instructions of that size.
	 *
	 * @param non-empty-list<array{float, int}> $points sorted by size
	 * @return non-empty-list<array{float, int}>|null the growth and the instructions of each point, null when the formula is not above 0 and finite at every size
	 */
	private function growth(ComplexityClass $class, array $points): ?array
	{
		try {
			$formula = $this->parser->parse($class->formula());
			$costs = array_map(fn (array $point): float => $this->evaluator->evaluateNumber($formula, ['n' => $point[0]]), $points);
		} catch (ExpressionException) {
			return null;
		}
		foreach ($costs as $cost) {
			if (!is_finite($cost) || $cost <= 0) {
				return null;
			}
		}

		return array_map(static fn (float $cost, array $point): array => [$cost / $costs[0], $point[1]], $costs, $points);
	}

	/**
	 * The largest growth of the points, which a tighter class allows less of
	 *
	 * @param non-empty-list<array{float, int}> $growth
	 */
	private function largest(array $growth): float
	{
		return max(array_column($growth, 0));
	}

	/**
	 * Whether no data set is slower than the smallest one's instructions times the growth, as
	 * Comparator::compareGrowth() decides.
	 *
	 * @param non-empty-list<array{float, int}> $growth the growth and the instructions of each point
	 */
	private function fits(array $growth, TolerancePolicy $tolerance): bool
	{
		foreach ($growth as [$times, $instructions]) {
			$expected = (int) round($growth[0][1] * $times);
			if ($this->comparator->compareMetric(Metric::Instructions, $expected, $instructions, $tolerance)->status === Status::Slower) {
				return false;
			}
		}

		return true;
	}

}
