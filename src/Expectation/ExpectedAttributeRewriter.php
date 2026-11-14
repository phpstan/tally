<?php declare(strict_types = 1);

namespace Tally\Expectation;

use InvalidArgumentException;
use Tally\Attribute\Expected;
use Tally\Attribute\ExpectedFor;
use Tally\Attribute\Polynomial;
use Tally\Complexity\ComplexityClass;
use Tally\Metric;
use Tally\Metrics;
use function array_values;
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
 * Finds the #[Expected] attribute of each benchmark method, or its #[ExpectedFor] of each data set,
 * in PHP code, using PHP's tokenizer, and the edits that rewrite one of them or replace the method's
 * complexity class. index() reads the whole code once, so a file with many cases is tokenized once,
 * not once per case. Everything else in the code stays byte for byte the same. A missing attribute
 * is added after the method's other attributes, and imported when the file does not import it yet.
 *
 * @phpstan-import-type Attribute from AttributeIndex
 * @phpstan-import-type Member from AttributeIndex
 * @phpstan-import-type Import from AttributeIndex
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
	public function locate(AttributeIndex $index, string $class, string $method, ?string $dataSet): int
	{
		$member = $this->member($index, $class, $method);
		$attribute = $this->findAttribute($member, $dataSet === null ? Expected::class : ExpectedFor::class, $dataSet);

		return $attribute !== null ? $attribute['line'] : $member['line'];
	}

	/**
	 * The edits of the indexed code that replace the attribute with the metrics, or add it with its
	 * import. Cases that add the same import get the same edit.
	 *
	 * @param Metrics $metrics every metric
	 * @param bool $namedArguments whether to write the data set as dataSet: '…'
	 * @return non-empty-list<CodeEdit>
	 * @throws MethodNotFoundException
	 * @throws ExpectationException
	 * @throws InvalidArgumentException when a metric is missing
	 */
	public function edits(AttributeIndex $index, string $class, string $method, ?string $dataSet, Metrics $metrics, bool $namedArguments): array
	{
		$arguments = $this->arguments($dataSet, $metrics, $namedArguments);
		$member = $this->member($index, $class, $method);
		$attributeClass = $dataSet === null ? Expected::class : ExpectedFor::class;
		$attribute = $this->findAttribute($member, $attributeClass, $dataSet);
		if ($attribute !== null) {
			return [new CodeEdit($attribute['start'], $attribute['end'] - $attribute['start'], $attribute['name'] . '(' . $arguments . ')')];
		}

		$name = $this->localName($index, $attributeClass);
		$imports = [];
		if ($name === null) {
			$name = $this->shortName($attributeClass);
			$imports = $this->importEdits($index, $index->imports, $attributeClass);
		}

		return [...$imports, new CodeEdit($member['insertAt'], 0, '#[' . $name . '(' . $arguments . ')]' . "\n" . $member['indent'])];
	}

	/**
	 * The edits of the indexed code that replace the complexity class of a benchmark method with
	 * another, with the same size argument as written. The import of the replaced class goes when
	 * nothing else uses it; the import of the new one takes its place when it sorts there.
	 *
	 * @param class-string<ComplexityClass> $current the class of the attribute to replace
	 * @return non-empty-list<CodeEdit>
	 * @throws MethodNotFoundException
	 * @throws ExpectationException
	 */
	public function complexityEdits(AttributeIndex $index, string $class, string $method, string $current, ComplexityClass $replacement): array
	{
		$attribute = $this->findAttribute($this->member($index, $class, $method), $current, null)
			?? throw new ExpectationException(sprintf('Method %s::%s() has no #[%s] attribute.', $class, $method, $this->shortName($current)));
		$arguments = $attribute['size'] ?? '';
		if ($replacement instanceof Polynomial) {
			$arguments .= ', degree: ' . $replacement->degree();
		}

		$edits = [];
		// a polynomial of another degree keeps the import
		$removed = $replacement::class === $current ? null : $this->unusedImport($index, $attribute);
		$imports = $index->imports;
		if ($removed !== null) {
			unset($imports[$removed[0]]);
		}
		$name = $this->localName($index, $replacement::class);
		if ($name === null) {
			$name = $this->shortName($replacement::class);
			if ($removed !== null && $this->sortsInPlace($index->imports, $removed[0], $replacement::class)) {
				$edits[] = new CodeEdit($removed[1]['start'], $removed[1]['end'] - $removed[1]['start'], 'use ' . $replacement::class . ';');
				$removed = null;
			} else {
				$edits = $this->importEdits($index, array_values($imports), $replacement::class);
			}
		}
		if ($removed !== null) {
			[, $old] = $removed;
			$edits[] = new CodeEdit($old['start'], $old['end'] - $old['start'] + (substr($index->code, $old['end'], 1) === "\n" ? 1 : 0), '');
		}

		return [...$edits, new CodeEdit($attribute['start'], $attribute['end'] - $attribute['start'], $name . '(' . $arguments . ')')];
	}

	/**
	 * The import of the attribute's class, when the attribute uses its alias and nothing else in
	 * the code does. An import among others in one use statement stays.
	 *
	 * @param Attribute $attribute
	 * @return array{int, Import}|null its key in the imports of the index, and the import
	 */
	private function unusedImport(AttributeIndex $index, array $attribute): ?array
	{
		foreach ($index->imports as $key => $import) {
			if (strcasecmp($import['name'], $attribute['class']) !== 0) {
				continue;
			}
			foreach ($index->imports as $other) {
				if ($other['start'] === $import['start'] && $other !== $import) {
					return null;
				}
			}

			if (strcasecmp($attribute['name'], $import['alias']) !== 0) {
				return null;
			}

			// the attribute's own name is one of the uses
			$alias = strtolower($import['alias']);

			return isset($index->names[$alias]) && $index->names[$alias] > 1 ? null : [$key, $import];
		}

		return null;
	}

	/**
	 * Whether the class sorts between the imports before and after the removed one.
	 *
	 * @param list<Import> $imports
	 * @param class-string $class
	 */
	private function sortsInPlace(array $imports, int $removed, string $class): bool
	{
		$previous = $imports[$removed - 1] ?? null;
		$next = $imports[$removed + 1] ?? null;

		return ($previous === null || (strtolower($previous['name']) <=> strtolower($class)) === -1)
			&& ($next === null || (strtolower($next['name']) <=> strtolower($class)) === 1);
	}

	/**
	 * Reads every class of the code once.
	 */
	public function index(string $code): AttributeIndex
	{
		return $this->scan(new Tokens($code), $code);
	}

	/**
	 * @return Member
	 * @throws MethodNotFoundException
	 * @throws ExpectationException
	 */
	private function member(AttributeIndex $index, string $class, string $method): array
	{
		$member = $index->members[strtolower($class . '::' . $method)] ?? throw new MethodNotFoundException($class, $method);
		foreach ($member['attributes'] as $attribute) {
			if (!$attribute['literal']) {
				throw new ExpectationException(sprintf('#[ExpectedFor] on line %d: write the data set as a string literal, so tally accept can find the attribute.', $attribute['line']));
			}
		}

		return $member;
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
	 * @param class-string $class
	 * @return Attribute|null
	 */
	private function findAttribute(array $member, string $class, ?string $dataSet): ?array
	{
		foreach ($member['attributes'] as $attribute) {
			if (strcasecmp($attribute['class'], $class) === 0 && $attribute['dataSet'] === $dataSet) {
				return $attribute;
			}
		}

		return null;
	}

	/**
	 * The name the code can use for the attribute, null when it needs an import.
	 *
	 * @param class-string $attributeClass
	 */
	private function localName(AttributeIndex $index, string $attributeClass): ?string
	{
		foreach ($index->imports as $import) {
			if (strcasecmp($import['name'], $attributeClass) === 0) {
				return $import['alias'];
			}
		}

		foreach ($index->imports as $import) {
			if (strcasecmp($import['alias'], $this->shortName($attributeClass)) === 0) {
				return '\\' . $attributeClass;
			}
		}

		return $index->namespace === null ? '\\' . $attributeClass : null;
	}

	/**
	 * @param class-string $class
	 */
	private function shortName(string $class): string
	{
		return substr($class, (int) strrpos($class, '\\') + 1);
	}

	/**
	 * Keeps the imports sorted, before the imports of functions and constants. The edits of
	 * #[Expected] and #[ExpectedFor] can go to the same place; in the natural order of their text,
	 * which is how ExpectationChanges applies edits at one place, they stay sorted too.
	 *
	 * @param list<Import> $imports the imports of the index to place it among
	 * @param class-string $attributeClass
	 * @return non-empty-list<CodeEdit>
	 */
	private function importEdits(AttributeIndex $index, array $imports, string $attributeClass): array
	{
		$statement = 'use ' . $attributeClass . ';';
		foreach ($imports as $import) {
			// 0 would be the attribute itself, which needs no import
			if ((strtolower($import['name']) <=> strtolower($attributeClass)) === 1) {
				return [new CodeEdit($import['start'], 0, $statement . "\n")];
			}
		}

		if ($imports !== []) {
			return [new CodeEdit($imports[count($imports) - 1]['end'], 0, "\n" . $statement)];
		}
		if ($index->otherUse !== null) {
			return [new CodeEdit($index->otherUse, 0, $statement . "\n")];
		}

		// localName() returns a fully qualified name without a namespace; the blank line is an edit
		// of its own, so the edits of both attributes add it once
		return [new CodeEdit((int) $index->namespaceEnd, 0, "\n"), new CodeEdit((int) $index->namespaceEnd, 0, "\n" . $statement)];
	}

	private function scan(Tokens $tokens, string $code): AttributeIndex
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
		$members = [];
		$names = [];

		$count = $tokens->count();
		for ($i = 0; $i < $count; $i++) {
			$token = $tokens->at($i);
			if ($token->isIgnorable()) {
				continue;
			}
			if ($token->is(T_STRING)) {
				$names[strtolower($token->text)] = ($names[strtolower($token->text)] ?? 0) + 1;
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
					$namespaceEnd = $tokens->at($tokens->skipTo($next, ';'))->pos + 1;
				}
				continue;
			}

			if ($depth === 0 && $token->is(T_USE)) {
				$end = $tokens->skipTo($i, ';');
				if ($tokens->at($tokens->next($i))->is([T_FUNCTION, T_CONST])) {
					$otherUse ??= $token->pos;
				} else {
					foreach ($this->parseImports($tokens, $i, $end) as [$name, $alias]) {
						$imports[] = ['name' => $name, 'alias' => $alias, 'start' => $token->pos, 'end' => $tokens->at($end)->pos + 1];
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
				for ($j = $i; $j < $groupEnd; $j++) {
					if (!$tokens->at($j)->is(T_STRING)) {
						continue;
					}

					$names[strtolower($tokens->at($j)->text)] = ($names[strtolower($tokens->at($j)->text)] ?? 0) + 1;
				}
				$insertAt = null;
				$i = $groupEnd;
				continue;
			}

			$insertAt ??= $i;

			if (!$token->is(T_FUNCTION)) {
				continue;
			}

			$indent = '';
			$previous = $tokens->at($insertAt - 1);
			if ($previous->is(T_WHITESPACE)) {
				$newline = strrpos($previous->text, "\n");
				$indent = $newline === false ? $previous->text : substr($previous->text, $newline + 1);
			}
			// the first declaration of the method counts, like PHP's
			$members[strtolower($currentClass . '::' . $tokens->at($tokens->next($i))->text)] ??= [
				'attributes' => $attributes,
				'insertAt' => $tokens->at($insertAt)->pos,
				'line' => $tokens->at($insertAt)->line,
				'indent' => $indent,
			];
			$attributes = [];
			$insertAt = null;
		}

		return new AttributeIndex($code, $namespace, $namespaceEnd, $imports, $otherUse, $members, $names);
	}

	/**
	 * @param list<Import> $imports
	 * @return array{list<Attribute>, int} the attributes of the group, and the index of its closing bracket
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

			// nothing after a trailing comma
			if ($tokens->next($segmentStart - 1) !== $i) {
				$attributes[] = $this->parseAttribute($tokens, $segmentStart, $i, $namespace, $aliases);
			}
			$segmentStart = $i + 1;
			if ($text === ']') {
				return [$attributes, $i];
			}
		}
	}

	/**
	 * @param array<string, string> $aliases
	 * @return Attribute
	 */
	private function parseAttribute(Tokens $tokens, int $start, int $end, ?string $namespace, array $aliases): array
	{
		$name = $tokens->next($start - 1);
		$resolved = $this->resolve($tokens->at($name)->text, $namespace, $aliases);
		$isExpectedFor = strcasecmp($resolved, ExpectedFor::class) === 0;

		$open = $tokens->next($name);
		$hasArguments = $tokens->at($open)->text === '(';
		$attributeEnd = $hasArguments ? $tokens->previous($end) : $name;
		$dataSet = null;
		if ($isExpectedFor) {
			$value = $hasArguments ? $this->dataSetArgument($tokens, $open, $attributeEnd) : null;
			$dataSet = $value === null ? null : $this->stringValue($tokens->at($value)->text);
		}

		return [
			'start' => $tokens->at($name)->pos,
			'end' => $tokens->at($attributeEnd)->pos + strlen($tokens->at($attributeEnd)->text),
			'name' => $tokens->at($name)->text,
			'class' => $resolved,
			'dataSet' => $dataSet,
			'size' => $hasArguments ? $this->sizeArgument($tokens, $open, $attributeEnd) : null,
			'line' => $tokens->at($name)->line,
			// member() rejects it only on the method it looks for
			'literal' => !$isExpectedFor || $dataSet !== null,
		];
	}

	/**
	 * The size argument of a complexity class as written: the first argument, unless it is named,
	 * else the one named size.
	 *
	 * @param int $open the opening parenthesis
	 * @param int $close the closing parenthesis
	 * @return string|null null when the attribute has no such argument
	 */
	private function sizeArgument(Tokens $tokens, int $open, int $close): ?string
	{
		$nesting = 0;
		$argumentStart = $open + 1;
		for ($i = $argumentStart; $i <= $close; $i++) {
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
			if ($text !== ',' && $i !== $close) {
				continue;
			}

			$first = $tokens->next($argumentStart - 1);
			$named = $tokens->at($first)->is(T_STRING) && $tokens->at($tokens->next($first))->text === ':';
			if ($first !== $i && (!$named || $tokens->at($first)->text === 'size')) {
				$argument = '';
				for ($j = $first; $j <= $tokens->previous($i); $j++) {
					$argument .= $tokens->at($j)->text;
				}

				return $argument;
			}
			$argumentStart = $i + 1;
		}

		return null;
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
