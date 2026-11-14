<?php declare(strict_types = 1);

namespace Tally\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tally\Attribute\RelativeTolerance;
use Tally\Configuration;

final class ConfigurationTest extends TestCase
{

	public function testDefaults(): void
	{
		$configuration = Configuration::create();

		$this->assertSame([], $configuration->paths());
		$this->assertSame([], $configuration->iniEntries());
		$this->assertSame(60, $configuration->nativeTimeLimitSeconds());
		$this->assertSame(1800, $configuration->countedTimeLimitSeconds());
		$this->assertSame('±2 % instructions, ±5 % cycles but at least ±2,000, ±5 % memory', $configuration->tolerance()->describe());
		$this->assertFalse($configuration->namedArguments());
	}

	public function testWithersKeepTheOriginal(): void
	{
		$original = Configuration::create();

		// each on the original, so that one changing its receiver shows
		$this->assertSame(['benchmarks'], $original->withPaths(['benchmarks'])->paths());
		$this->assertSame(['memory_limit=-1'], $original->withIniEntries(['memory_limit=-1'])->iniEntries());
		$this->assertSame(5, $original->withTimeLimits(5, 300)->nativeTimeLimitSeconds());
		$this->assertSame(300, $original->withTimeLimits(5, 300)->countedTimeLimitSeconds());
		$this->assertSame('±30 % of each metric', $original->withTolerance(new RelativeTolerance(0.3))->tolerance()->describe());
		$this->assertTrue($original->withNamedArguments(true)->namedArguments());
		$this->assertEquals(Configuration::create(), $original);
	}

}
