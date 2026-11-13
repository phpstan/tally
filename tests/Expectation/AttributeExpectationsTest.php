<?php declare(strict_types = 1);

namespace PHPStan\Tally\Expectation;

use PHPStan\Tally\Expectation\data\SortBench;
use PHPStan\Tally\Metrics;
use PHPUnit\Framework\TestCase;
use function copy;
use function file_get_contents;
use function str_contains;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

class AttributeExpectationsTest extends TestCase
{

	public function testWriteRewritesTheFileAndReadsBackWhatItWrote(): void
	{
		$file = tempnam(sys_get_temp_dir(), 'tally');
		self::assertIsString($file);
		copy(__DIR__ . '/data/SortBench.php', $file);

		try {
			$source = new AttributeExpectations($file, SortBench::class, 'benchSort', null, Metrics::fromArray(['instructions' => 1000]));
			$this->assertSame(['instructions' => 1000], $source->read()?->toArray());

			$source->write(Metrics::fromArray(['instructions' => 3_000]));

			$this->assertSame(['instructions' => 3_000], $source->read()?->toArray());
			$this->assertTrue(str_contains((string) file_get_contents($file), "#[Expected(instructions: '3.000K')]"));
			$this->assertEquals(new SourceLocation($file, 12), $source->location());
		} finally {
			unlink($file);
		}
	}

}
