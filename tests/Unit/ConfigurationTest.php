<?php declare(strict_types = 1);

namespace Tally\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tally\Configuration;

final class ConfigurationTest extends TestCase
{

	public function testWithPathsKeepsTheOriginal(): void
	{
		$original = Configuration::create();
		$configured = $original->withPaths(['benchmarks']);

		$this->assertSame([], $original->paths());
		$this->assertSame(['benchmarks'], $configured->paths());
	}

}
