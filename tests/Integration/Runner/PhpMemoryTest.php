<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Runner;

use PHPUnit\Framework\TestCase;
use Tally\Runner\PhpMemory;
use function str_repeat;
use function strlen;

/**
 * The margins are megabytes: coverage collection allocates a little whenever a line runs for the
 * first time, so exact values differ with and without it.
 */
final class PhpMemoryTest extends TestCase
{

	private const MIB = 1024 * 1024;

	public function testResettingForgetsAnEarlierPeak(): void
	{
		$memory = new PhpMemory();
		$earlier = str_repeat('x', 8 * self::MIB);
		$this->assertSame(8 * self::MIB, strlen($earlier));
		unset($earlier);

		$memory->resetPeak();
		$before = $memory->usage();

		$this->assertGreaterThanOrEqual($before, $memory->peak());
		$this->assertLessThan($before + self::MIB, $memory->peak());
	}

	public function testReadsTheUsageAndThePeak(): void
	{
		$memory = new PhpMemory();
		$memory->resetPeak();
		$before = $memory->usage();

		$allocated = str_repeat('x', self::MIB);
		$this->assertSame(self::MIB, strlen($allocated));
		$this->assertGreaterThanOrEqual($before + self::MIB, $memory->usage());
		unset($allocated);

		$this->assertLessThan($before + self::MIB, $memory->usage());
		$this->assertGreaterThanOrEqual($before + self::MIB, $memory->peak());
	}

}
