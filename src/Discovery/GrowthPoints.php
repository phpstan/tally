<?php declare(strict_types = 1);

namespace Tally\Discovery;

use ReflectionMethod;
use Tally\CaseId;
use Tally\Complexity\ComplexityClass;
use Tally\Complexity\GrowthPoint;
use Tally\DataSet;
use Tally\Expression\Evaluator;
use Tally\Expression\Expression;
use Tally\Expression\ExpressionException;
use Tally\Expression\Parser;
use Tally\Expression\TypeChecker;
use Tally\Expression\ValueType;
use function array_map;
use function array_search;
use function array_shift;
use function array_values;
use function count;
use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function min;
use function sprintf;

/**
 * Places the data sets of a benchmark with a complexity class on its growth curve: the size of
 * each, from the size expression and the data set's arguments, and how many times the formula at
 * that size is larger than at the smallest one. Every mistake in the expressions or the data sets
 * is found here, at discovery, so tally list reports it before anything is counted.
 */
final class GrowthPoints
{

	public function __construct(private Parser $parser, private TypeChecker $checker, private Evaluator $evaluator)
	{
	}

	/**
	 * @param string $name of the complexity class's attribute, such as #[Linear]
	 * @param list<DataSet> $dataSets
	 * @return list<GrowthPoint> in the order of the data sets
	 * @throws InvalidBenchmarkException
	 */
	public function points(string $className, ReflectionMethod $method, ComplexityClass $complexity, string $name, array $dataSets, string $where): array
	{
		$size = $this->parse($complexity->size(), $name . ' size', $where);
		$formula = $this->parse($complexity->formula(), $name . ' formula', $where);
		$this->checkTypes($method, $size, $formula, $name, $where);
		if (count($dataSets) < 2) {
			throw new InvalidBenchmarkException($where, sprintf('%s needs data sets of at least two sizes, the data provider yields %d.', $name, count($dataSets)));
		}

		$sizes = [];
		foreach ($dataSets as $dataSet) {
			$n = $this->size($method, $size, $dataSet, $name, $where);
			$other = array_search($n, $sizes, true);
			if ($other !== false) {
				throw new InvalidBenchmarkException($where, sprintf('data sets %s and %s both have size %s, %s needs each data set to have another size.', $other, $dataSet->name, $n, $name));
			}

			$sizes[$dataSet->name] = $n;
		}

		// the base is the smallest size, distinct from the others
		$baseSize = min($sizes);
		$baseName = (string) array_search($baseSize, $sizes, true);
		$baseCost = $this->cost($formula, $baseSize, $baseName, $name, $where);
		$baseId = new CaseId($className, $method->getName(), $baseName);

		return array_map(
			fn (DataSet $dataSet, float $n): GrowthPoint => new GrowthPoint($complexity->describe(), $baseId, $this->cost($formula, $n, $dataSet->name, $name, $where) / $baseCost),
			$dataSets,
			array_values($sizes),
		);
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	private function parse(string $source, string $label, string $where): Expression
	{
		try {
			return $this->parser->parse($source);
		} catch (ExpressionException $e) {
			throw new InvalidBenchmarkException($where, $label . ': ' . $e->getMessage());
		}
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	private function checkTypes(ReflectionMethod $method, Expression $size, Expression $formula, string $name, string $where): void
	{
		$types = [];
		foreach ($method->getParameters() as $parameter) {
			$types[$parameter->getName()] = $parameter->isVariadic() ? ValueType::Array : ValueType::ofReflection($parameter->getType());
		}

		try {
			$sizeType = $this->checker->check($size, $types);
		} catch (ExpressionException $e) {
			throw new InvalidBenchmarkException($where, $name . ' size: ' . $e->getMessage());
		}
		if (!$sizeType->mayBe(ValueType::Number) && !$sizeType->mayBe(ValueType::Array)) {
			throw new InvalidBenchmarkException($where, sprintf('%s size %s is %s, it must be a number or an array, whose count is the size.', $name, $size->source, $sizeType->value));
		}

		try {
			$this->checker->check($formula, ['n' => ValueType::Number]);
		} catch (ExpressionException $e) {
			throw new InvalidBenchmarkException($where, $name . ' formula: ' . $e->getMessage());
		}
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	private function size(ReflectionMethod $method, Expression $size, DataSet $dataSet, string $name, string $where): float
	{
		$remaining = $dataSet->arguments;
		$variables = [];
		foreach ($method->getParameters() as $parameter) {
			$variables[$parameter->getName()] = $parameter->isVariadic() ? $remaining : array_shift($remaining);
		}

		try {
			$value = $this->evaluator->evaluate($size, $variables);
		} catch (ExpressionException $e) {
			throw new InvalidBenchmarkException($where, sprintf('%s size in data set %s: %s', $name, $dataSet->name, $e->getMessage()));
		}
		if (is_array($value)) {
			return count($value);
		}
		if (!is_int($value) && !is_float($value)) {
			throw new InvalidBenchmarkException($where, sprintf(
				'%s size %s is %s in data set %s, it must be a number or an array, whose count is the size.',
				$name,
				$size->source,
				ValueType::ofValue($value)->value,
				$dataSet->name,
			));
		}

		return $value;
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	private function cost(Expression $formula, float $n, string $dataSet, string $name, string $where): float
	{
		try {
			$cost = $this->evaluator->evaluateNumber($formula, ['n' => $n]);
		} catch (ExpressionException $e) {
			throw new InvalidBenchmarkException($where, sprintf('%s formula at size %s of data set %s: %s', $name, $n, $dataSet, $e->getMessage()));
		}
		if (!is_finite($cost) || $cost <= 0) {
			throw new InvalidBenchmarkException($where, sprintf(
				'%s formula %s is %s at size %s of data set %s, it must be above 0 and finite at every size.',
				$name,
				$formula->source,
				$cost,
				$n,
				$dataSet,
			));
		}

		return $cost;
	}

}
