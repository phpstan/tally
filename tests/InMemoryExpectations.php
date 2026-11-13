<?php declare(strict_types = 1);

namespace Tally\Tests;

use Override;
use Tally\Expectation\ExpectationSource;
use Tally\Expectation\SourceLocation;
use Tally\Metrics;
use function json_encode;
use const JSON_THROW_ON_ERROR;

/**
 * Keeps the expectations of a data set in memory, for tests. Rewriting appends a line with the
 * measured metrics to the code.
 */
final class InMemoryExpectations implements ExpectationSource
{

	public function __construct(private ?Metrics $metrics, private string $file)
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
		return $code . json_encode($measured->toArray(), JSON_THROW_ON_ERROR) . "\n";
	}

	#[Override]
	public function location(): SourceLocation
	{
		return new SourceLocation($this->file, 1);
	}

}
