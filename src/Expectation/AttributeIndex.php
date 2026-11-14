<?php declare(strict_types = 1);

namespace Tally\Expectation;

/**
 * Where the #[Expected] and #[ExpectedFor] attributes of every method of a file are, and where
 * missing ones and their imports go, found by one pass of ExpectedAttributeRewriter over the
 * code. Offsets are in bytes of the code.
 *
 * @phpstan-type Attribute array{start: int, end: int, name: string, dataSet: string|null, line: int, literal: bool}
 * @phpstan-type Member array{attributes: list<Attribute>, insertAt: int, line: int, indent: string}
 * @phpstan-type Import array{name: string, alias: string, start: int, end: int}
 */
final class AttributeIndex
{

	/**
	 * @param string $code what the index describes
	 * @param int|null $namespaceEnd after the semicolon of the namespace declaration
	 * @param list<Import> $imports the class imports, from the use keyword to after the semicolon
	 * @param int|null $otherUse the first import of a function or a constant
	 * @param array<string, Member> $members the methods by lowercase Class::method, the attributes
	 *     before each, and where an attribute goes after them, with its line and indentation
	 */
	public function __construct(
		public readonly string $code,
		public readonly ?string $namespace,
		public readonly ?int $namespaceEnd,
		public readonly array $imports,
		public readonly ?int $otherUse,
		public readonly array $members
	)
	{
	}

}
