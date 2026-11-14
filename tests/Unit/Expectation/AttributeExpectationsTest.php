<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Expectation;

use PHPUnit\Framework\TestCase;
use Tally\Expectation\AttributeExpectations;
use Tally\Expectation\CodeEdit;
use Tally\Expectation\ExpectationException;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Expectation\SourceLocation;
use Tally\Metrics;

final class AttributeExpectationsTest extends TestCase
{

	private const CODE = "<?php\nuse Tally\\Attribute\\Expected;\nclass FooBench {\n\t#[Expected(instructions: '1', cycles: '1', memory: '0B')]\n\tpublic function bench() {}\n}\n";

	/**
	 * @throws ExpectationException
	 */
	public function testEditsTheAttributeInTheIndexedCode(): void
	{
		$source = self::source();

		$this->assertSame('/FooBench.php', $source->file());
		$this->assertSame(['instructions' => 1, 'cycles' => 1, 'memory' => 0], $source->read()?->toArray());
		$this->assertEquals(
			[new CodeEdit(56, 54, "Expected(instructions: '3.000K', cycles: '3.300K', memory: '1.000KiB')")],
			$source->edits(self::CODE, Metrics::fromArray(['instructions' => 3_000, 'cycles' => 3_300, 'memory' => 1024])),
		);
		$this->assertEquals(new SourceLocation('/FooBench.php', 4), $source->location());
	}

	/**
	 * @throws ExpectationException
	 */
	public function testRefusesCodeThatChangedSinceTheIndex(): void
	{
		$this->expectException(ExpectationException::class);
		$this->expectExceptionMessage('/FooBench.php changed after Tally loaded it, run the command again.');
		self::source()->edits(self::CODE . "\n", Metrics::fromArray(['instructions' => 3_000, 'cycles' => 3_300, 'memory' => 1024]));
	}

	private static function source(): AttributeExpectations
	{
		$rewriter = new ExpectedAttributeRewriter();

		return new AttributeExpectations($rewriter, '/FooBench.php', $rewriter->index(self::CODE), 'FooBench', 'bench', null, Metrics::fromArray(['instructions' => 1, 'cycles' => 1, 'memory' => 0]), false);
	}

}
