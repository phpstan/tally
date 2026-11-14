<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Expectation;

use PHPUnit\Framework\TestCase;
use Tally\Expectation\AttributeExpectations;
use Tally\Expectation\ExpectationException;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Expectation\SourceLocation;
use Tally\Metrics;
use Tally\Tests\Fixtures\Rewriter\SortBench;
use function file_get_contents;
use function sprintf;
use function str_replace;

final class AttributeExpectationsTest extends TestCase
{

	private const FILE = __DIR__ . '/../../Fixtures/Rewriter/SortBench.php';

	/**
	 * @throws ExpectationException
	 */
	public function testRewritesTheAttributeInTheCodeItGets(): void
	{
		$source = new AttributeExpectations(new ExpectedAttributeRewriter(), self::FILE, SortBench::class, 'benchSort', null, Metrics::fromArray(['instructions' => 1000, 'cycles' => 1100, 'memory' => 0]), false);
		$code = (string) file_get_contents(self::FILE);

		$this->assertSame(self::FILE, $source->file());
		$this->assertSame(
			str_replace("#[Expected(instructions: '1.000K', cycles: '1.100K', memory: '0B')]", "#[Expected(instructions: '3.000K', cycles: '3.300K', memory: '1.000KiB')]", $code),
			$source->rewrite($code, Metrics::fromArray(['instructions' => 3_000, 'cycles' => 3_300, 'memory' => 1024])),
		);
		// rewriting writes nothing and changes nothing
		$this->assertSame($code, file_get_contents(self::FILE));
		$this->assertSame(['instructions' => 1000, 'cycles' => 1100, 'memory' => 0], $source->read()?->toArray());
		$this->assertEquals(new SourceLocation(self::FILE, 13), $source->location());
	}

	/**
	 * @throws ExpectationException
	 */
	public function testLocationFailsForAMissingFile(): void
	{
		$file = __DIR__ . '/../../Fixtures/Rewriter/MissingBench.php';
		$source = new AttributeExpectations(new ExpectedAttributeRewriter(), $file, SortBench::class, 'benchSort', null, null, false);

		$this->expectException(ExpectationException::class);
		$this->expectExceptionMessage(sprintf('Cannot read %s.', $file));
		$source->location();
	}

}
