<?php declare(strict_types = 1);

namespace Tally\Expression;

/**
 * A node of the syntax tree of an expression
 *
 * @phpstan-sealed NumberNode|VariableNode|KeyNode|CallNode|NegateNode|BinaryNode
 */
interface Node
{

	/** Where the node is in the source, without the parentheses around it */
	public function span(): Span;

}
