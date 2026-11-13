<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Runner;

use PHPUnit\Framework\TestCase;
use Tally\Runner\PhpMemory;
use function str_repeat;
use function strlen;

final class PhpMemoryTest extends TestCase
{

	public function testReadsAndResetsPhpsMemoryUsage(): void
	{
		$memory = new PhpMemory();
		$memory->resetPeak();
		$before = $memory->usage();
		$this->assertSame($before, $memory->peak());

		$allocated = str_repeat('x', 1024 * 1024);
		$this->assertSame(1024 * 1024, strlen($allocated));
		$this->assertGreaterThanOrEqual($before + 1024 * 1024, $memory->usage());
		unset($allocated);
		$this->assertLessThan($before + 1024 * 1024, $memory->usage());
		$this->assertGreaterThanOrEqual($before + 1024 * 1024, $memory->peak());

		$memory->resetPeak();
		$this->assertLessThan($before + 1024 * 1024, $memory->peak());
	}

}
