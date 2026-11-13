<?php declare(strict_types = 1);

namespace PHPStan\Tally\Expectation;

use PHPStan\Tally\Attribute\Expected;
use PHPStan\Tally\Metric;
use PHPStan\Tally\Metrics;
use PhpToken;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function max;
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
use const T_DOUBLE_COLON;
use const T_FUNCTION;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAMESPACE;
use const T_NEW;
use const T_STRING;
use const T_USE;
use const T_WHITESPACE;

/**
 * Finds and rewrites the #[Expected] attribute of one benchmark method in PHP code, using PHP's
 * tokenizer. Everything else in the code stays byte for byte the same. A missing attribute is
 * added after the method's other attributes, and imported when the file does not import it yet.
 *
 * @phpstan-type Attribute array{start: int, end: int, name: string, dataSet: string|null, line: int}
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
	 */
	public function locate(string $code, string $class, string $method, ?string $dataSet): int
	{
		$tokens = array_values(PhpToken::tokenize($code));
		$member = $this->scan($tokens, $class, $method)['member'];
		$attribute = $this->findAttribute($member, $dataSet);

		return $attribute !== null ? $attribute['line'] : $tokens[$member['insertAt']]->line;
	}

	/**
	 * @throws MethodNotFoundException
	 */
	public function rewrite(string $code, string $class, string $method, ?string $dataSet, Metrics $metrics): string
	{
		$tokens = array_values(PhpToken::tokenize($code));
		$scan = $this->scan($tokens, $class, $method);
		$attribute = $this->findAttribute($scan['member'], $dataSet);
		$arguments = $this->arguments($dataSet, $metrics);

		if ($attribute !== null) {
			$start = $tokens[$attribute['start']]->pos;
			$end = $tokens[$attribute['end']]->pos + strlen($tokens[$attribute['end']]->text);

			return substr_replace($code, $attribute['name'] . '(' . $arguments . ')', $start, $end - $start);
		}

		$edits = [];
		$name = $this->localName($scan);
		if ($name === null) {
			$name = 'Expected';
			$edits[] = $this->importEdit($tokens, $scan);
		}

		$insertAt = $scan['member']['insertAt'];
		$indent = '';
		$previous = $tokens[$insertAt - 1] ?? null;
		if ($previous !== null && $previous->is(T_WHITESPACE)) {
			$newline = strrpos($previous->text, "\n");
			$indent = $newline === false ? $previous->text : substr($previous->text, $newline + 1);
		}
		$edits[] = [$tokens[$insertAt]->pos, '#[' . $name . '(' . $arguments . ')]' . "\n" . $indent];

		usort($edits, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
		foreach ($edits as [$offset, $text]) {
			$code = substr_replace($code, $text, $offset, 0);
		}

		return $code;
	}

	private function arguments(?string $dataSet, Metrics $metrics): string
	{
		$arguments = [];
		if ($dataSet !== null) {
			$arguments[] = 'dataSet: ' . var_export($dataSet, true);
		}
		foreach (Metric::cases() as $metric) {
			$value = $metrics->get($metric);
			if ($value === null) {
				continue;
			}

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
	 */
	private function localName(array $scan): ?string
	{
		foreach ($scan['imports'] as $import) {
			if (strcasecmp($import['name'], Expected::class) === 0) {
				return $import['alias'];
			}
		}

		$namespace = $scan['namespace'];
		if ($namespace !== null && strcasecmp($namespace . '\\Expected', Expected::class) === 0) {
			return 'Expected';
		}

		foreach ($scan['imports'] as $import) {
			if (strcasecmp($import['alias'], 'Expected') === 0) {
				return '\\' . Expected::class;
			}
		}

		return $namespace === null ? '\\' . Expected::class : null;
	}

	/**
	 * Keeps the imports sorted, before the imports of functions and constants.
	 *
	 * @param list<PhpToken> $tokens
	 * @param Scan $scan
	 * @return array{int, string}
	 */
	private function importEdit(array $tokens, array $scan): array
	{
		$statement = 'use ' . Expected::class . ';';
		foreach ($scan['imports'] as $import) {
			if (strcasecmp($import['name'], Expected::class) > 0) {
				return [$tokens[$import['start']]->pos, $statement . "\n"];
			}
		}

		$imports = $scan['imports'];
		if ($imports !== []) {
			return [$tokens[$imports[count($imports) - 1]['end']]->pos + 1, "\n" . $statement];
		}
		if ($scan['otherUse'] !== null) {
			return [$tokens[$scan['otherUse']]->pos, $statement . "\n"];
		}

		// localName() returns a fully qualified name without a namespace
		return [$tokens[(int) $scan['namespaceEnd']]->pos + 1, "\n\n" . $statement];
	}

	/**
	 * @param list<PhpToken> $tokens
	 * @return Scan
	 * @throws MethodNotFoundException
	 */
	private function scan(array $tokens, string $class, string $method): array
	{
		$namespace = null;
		$namespaceEnd = null;
		$imports = [];
		$otherUse = null;
		$depth = 0;
		$pendingClass = null;
		$currentClass = null;
		$classDepth = 0;
		$attributes = [];
		$insertAt = null;

		$count = count($tokens);
		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];
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
				if ($currentClass !== null && $depth === $classDepth) {
					$currentClass = null;
				}
				$depth--;
				$attributes = [];
				$insertAt = null;
				continue;
			}

			if ($depth === 0 && $token->is(T_NAMESPACE)) {
				$next = $this->next($tokens, $i);
				if ($next !== null && $tokens[$next]->is([T_STRING, T_NAME_QUALIFIED])) {
					$namespace = $tokens[$next]->text;
					$namespaceEnd = $this->skipTo($tokens, $next, ';');
				}
				continue;
			}

			if ($depth === 0 && $token->is(T_USE)) {
				$end = $this->skipTo($tokens, $i, ';');
				$next = $this->next($tokens, $i);
				if ($next !== null && $tokens[$next]->is([T_FUNCTION, T_CONST])) {
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
				$previous = $this->previous($tokens, $i);
				$next = $this->next($tokens, $i);
				if (
					($previous === null || !$tokens[$previous]->is([T_DOUBLE_COLON, T_NEW]))
					&& $next !== null
					&& $tokens[$next]->is(T_STRING)
				) {
					$pendingClass = $namespace === null ? $tokens[$next]->text : $namespace . '\\' . $tokens[$next]->text;
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

			$name = $this->next($tokens, $i);
			if (
				$name !== null
				&& strcasecmp($currentClass, $class) === 0
				&& strcasecmp($tokens[$name]->text, $method) === 0
			) {
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
	 * @param list<PhpToken> $tokens
	 * @param list<Import> $imports
	 * @return array{list<Attribute>, int} the #[Expected] attributes of the group, and the index of its closing bracket
	 */
	private function parseAttributeGroup(array $tokens, int $start, ?string $namespace, array $imports): array
	{
		$aliases = [];
		foreach ($imports as $import) {
			$aliases[strtolower($import['alias'])] = $import['name'];
		}

		$attributes = [];
		$nesting = 0;
		$segmentStart = $start + 1;
		for ($i = $start + 1; $i < count($tokens); $i++) {
			$text = $tokens[$i]->text;
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

		return [$attributes, count($tokens) - 1];
	}

	/**
	 * @param list<PhpToken> $tokens
	 * @param array<string, string> $aliases
	 * @return Attribute|null
	 */
	private function parseAttribute(array $tokens, int $start, int $end, ?string $namespace, array $aliases): ?array
	{
		$name = $this->next($tokens, $start - 1);
		if ($name === null || $name >= $end || !$tokens[$name]->is(self::NAME_TOKENS)) {
			return null;
		}
		if (strcasecmp($this->resolve($tokens[$name]->text, $namespace, $aliases), Expected::class) !== 0) {
			return null;
		}

		$attributeEnd = $name;
		$dataSet = null;
		$open = $this->next($tokens, $name);
		if ($open !== null && $open < $end && $tokens[$open]->text === '(') {
			$attributeEnd = $this->previous($tokens, $end) ?? $name;
			for ($i = $open + 1; $i < $attributeEnd; $i++) {
				if (!$tokens[$i]->is(T_STRING) || $tokens[$i]->text !== 'dataSet') {
					continue;
				}
				$colon = $this->next($tokens, $i);
				$value = $colon === null ? null : $this->next($tokens, $colon);
				if ($colon === null || $value === null || $tokens[$colon]->text !== ':' || !$tokens[$value]->is(T_CONSTANT_ENCAPSED_STRING)) {
					continue;
				}

				$dataSet = $this->stringValue($tokens[$value]->text);
			}
		}

		return [
			'start' => $name,
			'end' => $attributeEnd,
			'name' => $tokens[$name]->text,
			'dataSet' => $dataSet,
			'line' => $tokens[$name]->line,
		];
	}

	/**
	 * @param list<PhpToken> $tokens
	 * @return list<array{string, string}> names and aliases
	 */
	private function parseImports(array $tokens, int $start, int $end): array
	{
		$imports = [];
		$name = null;
		$alias = null;
		for ($i = $start + 1; $i <= $end; $i++) {
			$token = $tokens[$i];
			if ($token->text === '{') {
				// group imports are not used for the attribute
				return $imports;
			}
			if ($token->is(self::NAME_TOKENS) && $name === null) {
				$name = $token->text;
			} elseif ($token->is(T_AS)) {
				$next = $this->next($tokens, $i);
				$alias = $next === null ? null : $tokens[$next]->text;
				$i = $next ?? $i;
			} elseif (($token->text === ',' || $token->text === ';') && $name !== null) {
				$name = substr($name, str_starts_with($name, '\\') ? 1 : 0);
				$parts = explode('\\', $name);
				$imports[] = [$name, $alias ?? $parts[count($parts) - 1]];
				$name = null;
				$alias = null;
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

	/**
	 * @param list<PhpToken> $tokens
	 */
	private function next(array $tokens, int $index): ?int
	{
		for ($i = $index + 1; $i < count($tokens); $i++) {
			if (!$tokens[$i]->isIgnorable()) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * @param list<PhpToken> $tokens
	 */
	private function previous(array $tokens, int $index): ?int
	{
		for ($i = $index - 1; $i >= 0; $i--) {
			if (!$tokens[$i]->isIgnorable()) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * @param list<PhpToken> $tokens
	 */
	private function skipTo(array $tokens, int $index, string $text): int
	{
		for ($i = $index; $i < count($tokens); $i++) {
			if ($tokens[$i]->text === $text) {
				return $i;
			}
		}

		return max(0, count($tokens) - 1);
	}

}
