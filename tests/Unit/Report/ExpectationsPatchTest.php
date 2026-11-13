<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Report;

use PHPUnit\Framework\TestCase;
use Tally\Expectation\ChangePlan;
use Tally\Expectation\FileChange;
use Tally\Report\ExpectationsPatch;
use Tally\Report\RelativePath;
use Tally\Report\UnifiedDiff;

final class ExpectationsPatchTest extends TestCase
{

	public function testOneDiffPerFileRelativeToTheWorkingDirectory(): void
	{
		$plan = new ChangePlan(
			[
				new FileChange('/repo/benchmarks/ABench.php', "a\n", "a\nb\n", ['A::bench']),
				new FileChange('/elsewhere/BBench.php', "c\n", "d\n", ['B::bench']),
				new FileChange('/repo/benchmarks/Unchanged.php', "e\n", "e\n", ['C::bench']),
			],
			['D::bench' => 'Cannot read /repo/benchmarks/DBench.php.'],
		);

		$this->assertSame(
			"--- a/benchmarks/ABench.php\n+++ b/benchmarks/ABench.php\n@@ -1 +1,2 @@\n a\n+b\n"
			. "--- a//elsewhere/BBench.php\n+++ b//elsewhere/BBench.php\n@@ -1 +1 @@\n-c\n+d\n",
			(new ExpectationsPatch(new UnifiedDiff(), new RelativePath()))->render($plan, '/repo'),
		);
	}

}
