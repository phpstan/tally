<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Build;

use Override;
use PHPStan\Testing\RuleTestCase;
use Tally\Build\PHPStan\NoDefaultPropertyValueRule;

/**
 * @extends RuleTestCase<NoDefaultPropertyValueRule>
 */
final class NoDefaultPropertyValueRuleTest extends RuleTestCase
{

	public function testReportsEveryPropertyWithADefault(): void
	{
		$this->analyse([__DIR__ . '/../../Fixtures/Build/defaults.php'], [
			['Property $withDefault has a default value. Set it in the constructor.', 8],
			['Property $nullDefault has a default value. Set it in the constructor.', 12],
		]);
	}

	#[Override]
	protected function getRule(): NoDefaultPropertyValueRule
	{
		return new NoDefaultPropertyValueRule();
	}

}
