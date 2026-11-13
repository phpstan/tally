<?php declare(strict_types = 1);

namespace Tally\Expectation;

use Override;
use Tally\Metrics;
use function file_get_contents;
use function sprintf;

/**
 * The #[Expected] attribute of a benchmark method, or the #[ExpectedFor] of one of its data sets.
 */
final class AttributeExpectations implements ExpectationSource
{

	public function __construct(
		private ExpectedAttributeRewriter $rewriter,
		private string $file,
		private string $class,
		private string $method,
		private ?string $dataSet,
		private ?Metrics $metrics
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
	public function rewrite(string $code, Metrics $measured): string
	{
		return $this->rewriter->rewrite($code, $this->class, $this->method, $this->dataSet, $measured);
	}

	#[Override]
	public function location(): SourceLocation
	{
		$code = @file_get_contents($this->file);
		if ($code === false) {
			throw new ExpectationException(sprintf('Cannot read %s.', $this->file));
		}

		return new SourceLocation($this->file, $this->rewriter->locate($code, $this->class, $this->method, $this->dataSet));
	}

}
