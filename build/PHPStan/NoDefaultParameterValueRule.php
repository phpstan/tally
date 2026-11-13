<?php declare(strict_types = 1);

namespace Tally\Build\PHPStan;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use function is_string;
use function sprintf;

/**
 * Tally passes every argument explicitly, so a call shows everything that decides its result.
 *
 * @implements Rule<FunctionLike>
 */
final class NoDefaultParameterValueRule implements Rule
{

	#[Override]
	public function getNodeType(): string
	{
		return FunctionLike::class;
	}

	/**
	 * @return list<IdentifierRuleError>
	 */
	#[Override]
	public function processNode(Node $node, Scope $scope): array
	{
		$errors = [];
		foreach ($node->getParams() as $param) {
			if ($param->default === null) {
				continue;
			}

			$name = $param->var instanceof Variable && is_string($param->var->name) ? $param->var->name : '';
			$errors[] = RuleErrorBuilder::message(sprintf('Parameter $%s has a default value. Make it required and pass it explicitly.', $name))
				->identifier('tally.parameterDefault')
				->line($param->getStartLine())
				->build();
		}

		return $errors;
	}

}
