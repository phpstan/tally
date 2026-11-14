<?php declare(strict_types = 1);

namespace Tally\Expectation;

use Override;
use Tally\Metrics;
use function sprintf;

/**
 * The #[Expected] attribute of a benchmark method, or the #[ExpectedFor] of one of its data sets,
 * found in the index of its file, which all cases of the file share.
 */
final class AttributeExpectations implements ExpectationSource
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
		private ?string $dataSet,
		private ?Metrics $metrics,
		private bool $namedArguments
	)
	{
	}

	#[Override]
	public function read(): ?Metrics
	{
		return $this->metrics;
	}

	#[Override]
	public function file(): string
	{
		return $this->file;
	}

	#[Override]
	public function edits(string $code, Metrics $measured): array
	{
		if ($code !== $this->index->code) {
			throw new ExpectationException(sprintf('%s changed after Tally loaded it, run the command again.', $this->file));
		}

		return $this->rewriter->edits($this->index, $this->class, $this->method, $this->dataSet, $measured, $this->namedArguments);
	}

	#[Override]
	public function location(): SourceLocation
	{
		return new SourceLocation($this->file, $this->rewriter->locate($this->index, $this->class, $this->method, $this->dataSet));
	}

}
