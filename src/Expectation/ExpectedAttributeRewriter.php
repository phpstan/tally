<?php declare(strict_types = 1);

namespace Tally\Expectation;

use InvalidArgumentException;
use Tally\Attribute\Expected;
use Tally\Attribute\ExpectedFor;
use Tally\Metric;
use Tally\Metrics;
use function count;
use function explode;
use function implode;
use function in_array;
use function ltrim;
use function sprintf;
use function str_starts_with;
use function strcasecmp;
use function stripcslashes;
use function strlen;
use function strrpos;
use function strtolower;
use function strtr;
use function substr;
use function substr_replace;
use function usort;
use function var_export;
use const T_AS;
use const T_ATTRIBUTE;
use const T_CLASS;
use const T_CONST;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_FUNCTION;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_STRING;
use const T_USE;
use const T_WHITESPACE;

/**
 * Finds and rewrites the #[Expected] attribute of one benchmark method, or its #[ExpectedFor] of
 * one data set, in PHP code, using PHP's tokenizer. Everything else in the code stays byte for byte
 * the same. A missing attribute is added after the method's other attributes, and imported when
 * the file does not import it yet.
 *
 * @phpstan-type Attribute array{start: int, end: int, name: string, dataSet: string|null, line: int, literal: bool}
 * @phpstan-type Member array{attributes: list<Attribute>, insertAt: int}
 * @phpstan-type Import array{name: string, alias: string, start: int, end: int}
 * @phpstan-type Scan array{namespace: string|null, namespaceEnd: int|null, imports: list<Import>, otherUse: int|null, member: Member}
 */
final class ExpectedAttributeRewriter
{

	private const NAME_TOKENS = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

	/**
	 * The line of the attribute, or of the method's declaration when it has none.
	 *
	 * @throws MethodNotFoundException
	 * @throws ExpectationException
	 */
	public function locate(string $code, string $class, string $method, ?string $dataSet): int
	{
		$tokens = new Tokens($code);
		$member = $this->scan($tokens, $class, $method)['member'];
		$attribute = $this->findAttribute($member, $dataSet);

		return $attribute !== null ? $attribute['line'] : $tokens->at($member['insertAt'])->line;
	}

	/**
	 * @param Metrics $metrics every metric
	 * @param bool $namedArguments whether to write the data set as dataSet: '…'
	 * @throws MethodNotFoundException
	 * @throws ExpectationException
	 * @throws InvalidArgumentException when a metric is missing
	 */
	public function rewrite(string $code, string $class, string $method, ?string $dataSet, Metrics $metrics, bool $namedArguments): string
	{
		$arguments = $this->arguments($dataSet, $metrics, $namedArguments);
		$tokens = new Tokens($code);
		$scan = $this->scan($tokens, $class, $method);
		$attribute = $this->findAttribute($scan['member'], $dataSet);
		$attributeClass = $dataSet === null ? Expected::class : ExpectedFor::class;

		if ($attribute !== null) {
			$start = $tokens->at($attribute['start'])->pos;
			$end = $tokens->at($attribute['end'])->pos + strlen($tokens->at($attribute['end'])->text);

			return substr_replace($code, $attribute['name'] . '(' . $arguments . ')', $start, $end - $start);
		}

		$edits = [];
		$name = $this->localName($scan, $attributeClass);
		if ($name === null) {
			$name = $this->shortName($attributeClass);
			$edits[] = $this->importEdit($tokens, $scan, $attributeClass);
		}

		$insertAt = $scan['member']['insertAt'];
		$indent = '';
		$previous = $tokens->at($insertAt - 1);
		if ($previous->is(T_WHITESPACE)) {
			$newline = strrpos($previous->text, "\n");
			$indent = $newline === false ? $previous->text : substr($previous->text, $newline + 1);
		}
		$edits[] = [$tokens->at($insertAt)->pos, '#[' . $name . '(' . $arguments . ')]' . "\n" . $indent];

		usort($edits, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
		foreach ($edits as [$offset, $text]) {
			$code = substr_replace($code, $text, $offset, 0);
		}

		return $code;
	}

	private function arguments(?string $dataSet, Metrics $metrics, bool $namedArguments): string
	{
		$arguments = [];
		if ($dataSet !== null) {
			$arguments[] = ($namedArguments ? 'dataSet: ' : '') . var_export($dataSet, true);
		}
		foreach (Metric::cases() as $metric) {
			$value = $metrics->get($metric) ?? throw new InvalidArgumentException(sprintf('The %s to write are missing.', $metric->value));
			$arguments[] = sprintf('%s: %s', $metric->value, var_export($metric->format()->format($value), true));
		}

		return implode(', ', $arguments);
	}

	/**
	 * @param Member $member
	 * @return Attribute|null
	 */
	private function findAttribute(array $member, ?string $dataSet): ?array
	{
		foreach ($member['attributes'] as $attribute) {
			if ($attribute['dataSet'] === $dataSet) {
				return $attribute;
			}
		}

		return null;
	}

	/**
	 * The name the code can use for the attribute, null when it needs an import.
	 *
	 * @param Scan $scan
	 * @param class-string $attributeClass
	 */
	private function localName(array $scan, string $attributeClass): ?string
	{
		foreach ($scan['imports'] as $import) {
			if (strcasecmp($import['name'], $attributeClass) === 0) {
				return $import['alias'];
			}
		}

		foreach ($scan['imports'] as $import) {
			if (strcasecmp($import['alias'], $this->shortName($attributeClass)) === 0) {
				return '\\' . $attributeClass;
			}
		}

		return $scan['namespace'] === null ? '\\' . $attributeClass : null;
	}

	/**
	 * @param class-string $class
	 */
	private function shortName(string $class): string
	{
		return substr($class, (int) strrpos($class, '\\') + 1);
	}

	/**
	 * Keeps the imports sorted, before the imports of functions and constants.
	 *
	 * @param Scan $scan
	 * @param class-string $attributeClass
	 * @return array{int, string}
	 */
	private function importEdit(Tokens $tokens, array $scan, string $attributeClass): array
	{
		$statement = 'use ' . $attributeClass . ';';
		foreach ($scan['imports'] as $import) {
			// 0 would be the attribute itself, which needs no import
			if ((strtolower($import['name']) <=> strtolower($attributeClass)) === 1) {
				return [$tokens->at($import['start'])->pos, $statement . "\n"];
			}
		}

		$imports = $scan['imports'];
		if ($imports !== []) {
			return [$tokens->at($imports[count($imports) - 1]['end'])->pos + 1, "\n" . $statement];
		}
		if ($scan['otherUse'] !== null) {
			return [$tokens->at($scan['otherUse'])->pos, $statement . "\n"];
		}

		// localName() returns a fully qualified name without a namespace
		return [$tokens->at((int) $scan['namespaceEnd'])->pos + 1, "\n\n" . $statement];
	}

	/**
	 * @return Scan
	 * @throws MethodNotFoundException
	 * @throws ExpectationException
	 */
	private function scan(Tokens $tokens, string $class, string $method): array
	{
		$namespace = null;
		$namespaceEnd = null;
		$imports = [];
		$otherUse = null;
		$depth = 0;
		$pendingClass = null;
		$currentClass = null;
		// the depth of the body of $currentClass
		$classDepth = null;
		$attributes = [];
		$insertAt = null;

		$count = $tokens->count();
		for ($i = 0; $i < $count; $i++) {
			$token = $tokens->at($i);
			if ($token->isIgnorable()) {
				continue;
			}

			if ($token->text === '{' || $token->is(T_DOLLAR_OPEN_CURLY_BRACES)) {
				$depth++;
				if ($pendingClass !== null && $token->text === '{') {
					$currentClass = $pendingClass;
					$classDepth = $depth;
					$pendingClass = null;
					$attributes = [];
					$insertAt = null;
				}
				continue;
			}

			if ($token->text === '}') {
				if ($depth === $classDepth) {
					$currentClass = null;
					$classDepth = null;
				}
				$depth--;
				$attributes = [];
				$insertAt = null;
				continue;
			}

			if ($depth === 0 && $token->is(T_NAMESPACE)) {
				$next = $tokens->next($i);
				// namespace { opens the global namespace
				if ($tokens->at($next)->is([T_STRING, T_NAME_QUALIFIED])) {
					$namespace = $tokens->at($next)->text;
					$namespaceEnd = $tokens->skipTo($next, ';');
				}
				continue;
			}

			if ($depth === 0 && $token->is(T_USE)) {
				$end = $tokens->skipTo($i, ';');
				if ($tokens->at($tokens->next($i))->is([T_FUNCTION, T_CONST])) {
					$otherUse ??= $i;
				} else {
					foreach ($this->parseImports($tokens, $i, $end) as [$name, $alias]) {
						$imports[] = ['name' => $name, 'alias' => $alias, 'start' => $i, 'end' => $end];
					}
				}
				$i = $end;
				continue;
			}

			if ($token->is(T_CLASS)) {
				$next = $tokens->next($i);
				// not Foo::class or new class
				if ($tokens->at($next)->is(T_STRING)) {
					$pendingClass = $namespace === null ? $tokens->at($next)->text : $namespace . '\\' . $tokens->at($next)->text;
				}
				continue;
			}

			if ($currentClass === null || $depth !== $classDepth) {
				continue;
			}

			if ($token->text === ';') {
				$attributes = [];
				$insertAt = null;
				continue;
			}

			if ($token->is(T_ATTRIBUTE)) {
				[$groupAttributes, $groupEnd] = $this->parseAttributeGroup($tokens, $i, $namespace, $imports);
				$attributes = [...$attributes, ...$groupAttributes];
				$insertAt = null;
				$i = $groupEnd;
				continue;
			}

			$insertAt ??= $i;

			if (!$token->is(T_FUNCTION)) {
				continue;
			}

			$name = $tokens->next($i);
			if (
				strcasecmp($currentClass, $class) === 0
				&& strcasecmp($tokens->at($name)->text, $method) === 0
			) {
				foreach ($attributes as $attribute) {
					if (!$attribute['literal']) {
						throw new ExpectationException(sprintf('#[ExpectedFor] on line %d: write the data set as a string literal, so tally accept can find the attribute.', $attribute['line']));
					}
				}

				return [
					'namespace' => $namespace,
					'namespaceEnd' => $namespaceEnd,
					'imports' => $imports,
					'otherUse' => $otherUse,
					'member' => ['attributes' => $attributes, 'insertAt' => $insertAt],
				];
			}
		}

		throw new MethodNotFoundException($class, $method);
	}

	/**
	 * @param list<Import> $imports
	 * @return array{list<Attribute>, int} the #[Expected] and #[ExpectedFor] attributes of the group, and the index of its closing bracket
	 * @throws ExpectationException
	 */
	private function parseAttributeGroup(Tokens $tokens, int $start, ?string $namespace, array $imports): array
	{
		$aliases = [];
		foreach ($imports as $import) {
			$aliases[strtolower($import['alias'])] = $import['name'];
		}

		$attributes = [];
		$nesting = 0;
		$segmentStart = $start + 1;
		// $start is #[
		for ($i = $start;; $i++) {
			$text = $tokens->at($i)->text;
			if (in_array($text, ['(', '['], true)) {
				$nesting++;
				continue;
			}
			if ($nesting > 0) {
				if (in_array($text, [')', ']'], true)) {
					$nesting--;
				}
				continue;
			}
			if ($text !== ',' && $text !== ']') {
				continue;
			}

			$attribute = $this->parseAttribute($tokens, $segmentStart, $i, $namespace, $aliases);
			if ($attribute !== null) {
				$attributes[] = $attribute;
			}
			$segmentStart = $i + 1;
			if ($text === ']') {
				return [$attributes, $i];
			}
		}
	}

	/**
	 * @param array<string, string> $aliases
	 * @return Attribute|null
	 * @throws ExpectationException
	 */
	private function parseAttribute(Tokens $tokens, int $start, int $end, ?string $namespace, array $aliases): ?array
	{
		// the closing bracket after a trailing comma, which is not the attribute either
		$name = $tokens->next($start - 1);
		$resolved = $this->resolve($tokens->at($name)->text, $namespace, $aliases);
		$isExpected = strcasecmp($resolved, Expected::class) === 0;
		if (!$isExpected && strcasecmp($resolved, ExpectedFor::class) !== 0) {
			return null;
		}

		$open = $tokens->next($name);
		$hasArguments = $tokens->at($open)->text === '(';
		$attributeEnd = $hasArguments ? $tokens->previous($end) : $name;
		$dataSet = null;
		if (!$isExpected) {
			$value = $hasArguments ? $this->dataSetArgument($tokens, $open, $attributeEnd) : null;
			$dataSet = $value === null ? null : $this->stringValue($tokens->at($value)->text);
		}

		return [
			'start' => $name,
			'end' => $attributeEnd,
			'name' => $tokens->at($name)->text,
			'dataSet' => $dataSet,
			'line' => $tokens->at($name)->line,
			// scan() rejects it only on the method it looks for
			'literal' => $isExpected || $dataSet !== null,
		];
	}

	/**
	 * The string literal of the data set: the named argument dataSet, or the first argument.
	 *
	 * @param int $open the opening parenthesis
	 * @param int $close the closing parenthesis
	 * @return int|null null when the data set is not a string literal
	 */
	private function dataSetArgument(Tokens $tokens, int $open, int $close): ?int
	{
		for ($i = $open; $i !== $close; $i++) {
			if ($tokens->at($i)->is(T_STRING) && $tokens->at($i)->text === 'dataSet') {
				$value = $tokens->next($tokens->next($i));

				return $this->isLiteralArgument($tokens, $value) ? $value : null;
			}
		}

		$first = $tokens->next($open);

		return $this->isLiteralArgument($tokens, $first) ? $first : null;
	}

	/** A string literal that is the whole argument, not the start of an expression */
	private function isLiteralArgument(Tokens $tokens, int $index): bool
	{
		return $tokens->at($index)->is(T_CONSTANT_ENCAPSED_STRING)
			&& in_array($tokens->at($tokens->next($index))->text, [',', ')'], true);
	}

	/**
	 * @return list<array{string, string}> names and aliases
	 */
	private function parseImports(Tokens $tokens, int $start, int $end): array
	{
		$imports = [];
		$prefix = '';
		$name = '';
		$alias = '';
		// $start is use
		for ($i = $start; $i <= $end; $i++) {
			$token = $tokens->at($i);
			if ($token->text === '{') {
				// use Prefix\{A, B as C};
				$prefix = $name . '\\';
				continue;
			}
			if ($token->is(self::NAME_TOKENS)) {
				$name = $prefix . ltrim($token->text, '\\');
				$parts = explode('\\', $name);
				$alias = $parts[count($parts) - 1];
			} elseif ($token->is(T_AS)) {
				$i = $tokens->next($i);
				$alias = $tokens->at($i)->text;
			} elseif ($token->text === ',' || $token->text === ';') {
				$imports[] = [$name, $alias];
			}
		}

		return $imports;
	}

	/**
	 * @param array<string, string> $aliases
	 */
	private function resolve(string $name, ?string $namespace, array $aliases): string
	{
		if (str_starts_with($name, '\\')) {
			return substr($name, 1);
		}

		$parts = explode('\\', $name, 2);
		$alias = strtolower($parts[0]);
		if (isset($aliases[$alias])) {
			return $aliases[$alias] . (isset($parts[1]) ? '\\' . $parts[1] : '');
		}

		return $namespace === null ? $name : $namespace . '\\' . $name;
	}

	private function stringValue(string $literal): string
	{
		$inner = substr($literal, 1, -1);

		return $literal[0] === "'" ? strtr($inner, ['\\\\' => '\\', "\\'" => "'"]) : stripcslashes($inner);
	}

}
