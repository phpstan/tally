<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Expectation;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tally\Expectation\AttributeExpectations;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Expectation\SourceLocation;
use Tally\Expectation\UnsupportedAttributeException;
use Tally\Metrics;
use Tally\Tests\Fixtures\Rewriter\SortBench;
use function chmod;
use function copy;
use function file_get_contents;
use function sprintf;
use function str_contains;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class AttributeExpectationsTest extends TestCase
{

	/**
	 * @throws UnsupportedAttributeException
	 */
	public function testWriteRewritesTheFileAndReadsBackWhatItWrote(): void
	{
		$file = tempnam(sys_get_temp_dir(), 'tally');
		self::assertIsString($file);
		copy(__DIR__ . '/../../Fixtures/Rewriter/SortBench.php', $file);

		try {
			$source = new AttributeExpectations(new ExpectedAttributeRewriter(), $file, SortBench::class, 'benchSort', null, Metrics::fromArray(['instructions' => 1000, 'cycles' => 1100, 'memory' => 0]));
			$this->assertSame(['instructions' => 1000, 'cycles' => 1100, 'memory' => 0], $source->read()?->toArray());

			$source->write(Metrics::fromArray(['instructions' => 3_000, 'cycles' => 3_300, 'memory' => 1024]));

			$this->assertSame(['instructions' => 3_000, 'cycles' => 3_300, 'memory' => 1024], $source->read()?->toArray());
			$this->assertTrue(str_contains((string) file_get_contents($file), "#[Expected(instructions: '3.000K', cycles: '3.300K', memory: '1.000KiB')]"));
			$this->assertEquals(new SourceLocation($file, 13), $source->location());
		} finally {
			unlink($file);
		}
	}

	/**
	 * @throws UnsupportedAttributeException
	 */
	public function testWriteFailsForAReadOnlyFile(): void
	{
		$file = tempnam(sys_get_temp_dir(), 'tally');
		self::assertIsString($file);
		copy(__DIR__ . '/../../Fixtures/Rewriter/SortBench.php', $file);
		chmod($file, 0444);

		try {
			$source = new AttributeExpectations(new ExpectedAttributeRewriter(), $file, SortBench::class, 'benchSort', null, null);
			try {
				$source->write(Metrics::fromArray(['instructions' => 3_000, 'cycles' => 3_300, 'memory' => 1024]));
				self::fail('Writing a read-only file succeeded.');
			} catch (RuntimeException $e) {
				$this->assertSame(sprintf('Cannot write %s.', $file), $e->getMessage());
			}
			// a failed write keeps what was read
			$this->assertNull($source->read());
		} finally {
			unlink($file);
		}
	}

	/**
	 * @throws UnsupportedAttributeException
	 */
	public function testLocationFailsForAMissingFile(): void
	{
		$file = __DIR__ . '/../../Fixtures/Rewriter/MissingBench.php';
		$source = new AttributeExpectations(new ExpectedAttributeRewriter(), $file, SortBench::class, 'benchSort', null, null);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(sprintf('Cannot read %s.', $file));
		$source->location();
	}

}
