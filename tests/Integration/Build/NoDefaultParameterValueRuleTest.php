<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Build;

use Override;
use PHPStan\Testing\RuleTestCase;
use Tally\Build\PHPStan\NoDefaultParameterValueRule;

/**
 * @extends RuleTestCase<NoDefaultParameterValueRule>
 */
final class NoDefaultParameterValueRuleTest extends RuleTestCase
{

	public function testReportsEveryParameterWithADefault(): void
	{
		$this->analyse([__DIR__ . '/../../Fixtures/Build/defaults.php'], [
			['Parameter $promoted has a default value. Make it required and pass it explicitly.', 14],
			['Parameter $b has a default value. Make it required and pass it explicitly.', 20],
			['Parameter $c has a default value. Make it required and pass it explicitly.', 22],
			['Parameter $e has a default value. Make it required and pass it explicitly.', 29],
		]);
	}

	#[Override]
	protected function getRule(): NoDefaultParameterValueRule
	{
		return new NoDefaultParameterValueRule();
	}

}
