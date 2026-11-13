<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Expression;

use Tally\Expression\BinaryNode;
use Tally\Expression\CallNode;
use Tally\Expression\KeyNode;
use Tally\Expression\NegateNode;
use Tally\Expression\Node;
use Tally\Expression\NumberNode;
use Tally\Expression\VariableNode;
use function array_map;
use function array_merge;
use function implode;
use function is_int;
use function var_export;

/** Writes a syntax tree as an S-expression, so a test shows its shape at a glance */
final class AstDump
{

	public function tree(Node $node): string
	{
		return match (true) {
			$node instanceof NumberNode => var_export($node->value, true),
			$node instanceof VariableNode => $node->name,
			$node instanceof KeyNode => '(key ' . $this->tree($node->array) . ' ' . (is_int($node->key) ? $node->key : "'" . $node->key . "'") . ')',
			$node instanceof CallNode => '(' . implode(' ', [$node->name, ...array_map($this->tree(...), $node->arguments)]) . ')',
			$node instanceof NegateNode => '(neg ' . $this->tree($node->operand) . ')',
			default => '(' . $node->operator->value . ' ' . $this->tree($node->left) . ' ' . $this->tree($node->right) . ')',
		};
	}

	/**
	 * The source text of every node's span, parents before children, with the key and name spans
	 * after the node they belong to.
	 *
	 * @return list<string>
	 */
	public function spans(Node $node, string $source): array
	{
		$own = [$node->span()->text($source)];

		return match (true) {
			$node instanceof KeyNode => array_merge($own, $this->spans($node->array, $source), ['key ' . $node->keySpan->text($source)]),
			$node instanceof CallNode => array_merge($own, ['name ' . $node->nameSpan->text($source)], ...array_map(fn (Node $argument): array => $this->spans($argument, $source), $node->arguments)),
			$node instanceof NegateNode => array_merge($own, $this->spans($node->operand, $source)),
			$node instanceof BinaryNode => array_merge($own, $this->spans($node->left, $source), $this->spans($node->right, $source)),
			default => $own,
		};
	}

}
