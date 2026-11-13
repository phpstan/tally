<?php declare(strict_types = 1);

namespace Tally\Tests\Unit;

use PHPUnit\Framework\TestCase;
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
	}

	public function testWithersKeepTheOriginal(): void
	{
		$original = Configuration::create();
		$configured = $original
			->withPaths(['benchmarks'])
			->withIniEntries(['memory_limit=-1'])
			->withTimeLimits(5, 300);

		$this->assertSame(['benchmarks'], $configured->paths());
		$this->assertSame(['memory_limit=-1'], $configured->iniEntries());
		$this->assertSame(5, $configured->nativeTimeLimitSeconds());
		$this->assertSame(300, $configured->countedTimeLimitSeconds());
		$this->assertEquals(Configuration::create(), $original);
	}

}
