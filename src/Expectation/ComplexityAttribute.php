<?php declare(strict_types = 1);

namespace Tally\Expectation;

use Tally\Complexity\ComplexityClass;

/**
 * The complexity class of a benchmark method, such as #[Linear], which tally accept replaces with
 * the preset the measured instructions grow as.
 */
final class ComplexityAttribute
{

	public function __construct(
		private ExpectedAttributeRewriter $rewriter,
		private string $file,
		private string $class,
		private string $method,
		public readonly ComplexityClass $complexity
	)
	{
	}

	/** The file that declares the method */
	public function file(): string
	{
		return $this->file;
	}

	/**
	 * The code of the file with the replacement instead of this complexity class.
	 *
	 * @throws ExpectationException
	 */
	public function rewrite(string $code, ComplexityClass $replacement): string
	{
		return $this->rewriter->replaceComplexity($code, $this->class, $this->method, $this->complexity::class, $replacement);
	}

}
