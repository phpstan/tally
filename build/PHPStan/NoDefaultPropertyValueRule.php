<?php declare(strict_types = 1);

namespace Tally\Build\PHPStan;

use Override;
use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use function sprintf;

/**
 * Properties get their values in the constructor, so the constructor shows the whole initial state.
 *
 * @implements Rule<Property>
 */
final class NoDefaultPropertyValueRule implements Rule
{

	#[Override]
	public function getNodeType(): string
	{
		return Property::class;
	}

	/**
	 * @return list<IdentifierRuleError>
	 */
	#[Override]
	public function processNode(Node $node, Scope $scope): array
	{
		$errors = [];
		foreach ($node->props as $property) {
			if ($property->default === null) {
				continue;
			}

			$errors[] = RuleErrorBuilder::message(sprintf('Property $%s has a default value. Set it in the constructor.', $property->name->toString()))
				->identifier('tally.propertyDefault')
				->line($property->getStartLine())
				->build();
		}

		return $errors;
	}

}
