<?php declare(strict_types = 1);

namespace Tally\Expectation;

use Tally\Complexity\ComplexityClass;
use function sprintf;

/**
 * The complexity class of a benchmark method, such as #[Linear], which tally accept replaces with
 * the preset the measured instructions grow as, found in the index of its file.
 */
final class ComplexityAttribute
{

	/**
	 * @param AttributeIndex $index of the file's code when the benchmarks were loaded
	 */
	public function __construct(
		private ExpectedAttributeRewriter $rewriter,
		private string $file,
		private AttributeIndex $index,
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
	 * The edits of the file's code that write the replacement instead of this complexity class.
	 *
	 * @return list<CodeEdit>
	 * @throws ExpectationException
	 */
	public function edits(string $code, ComplexityClass $replacement): array
	{
		if ($code !== $this->index->code) {
			throw new ExpectationException(sprintf('%s changed after Tally loaded it, run the command again.', $this->file));
		}

		return $this->rewriter->complexityEdits($this->index, $this->class, $this->method, $this->complexity::class, $replacement);
	}

}
