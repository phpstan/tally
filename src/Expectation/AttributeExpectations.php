<?php declare(strict_types = 1);

namespace Tally\Expectation;

use RuntimeException;
use Tally\Metrics;
use function file_get_contents;
use function file_put_contents;
use function sprintf;

/**
 * The #[Expected] attribute of a benchmark method, or the one of its data set.
 */
final class AttributeExpectations implements ExpectationSource
{

	private ExpectedAttributeRewriter $rewriter;

	public function __construct(
		private string $file,
		private string $class,
		private string $method,
		private ?string $dataSet,
		private ?Metrics $metrics
	)
	{
		$this->rewriter = new ExpectedAttributeRewriter();
	}

	public function read(): ?Metrics
	{
		return $this->metrics;
	}

	public function write(Metrics $measured): void
	{
		$code = $this->rewriter->rewrite($this->readFile(), $this->class, $this->method, $this->dataSet, $measured);
		if (file_put_contents($this->file, $code) === false) {
			throw new RuntimeException(sprintf('Cannot write %s.', $this->file));
		}

		$this->metrics = $measured;
	}

	public function location(): SourceLocation
	{
		return new SourceLocation($this->file, $this->rewriter->locate($this->readFile(), $this->class, $this->method, $this->dataSet));
	}

	private function readFile(): string
	{
		$code = file_get_contents($this->file);
		if ($code === false) {
			throw new RuntimeException(sprintf('Cannot read %s.', $this->file));
		}

		return $code;
	}

}
