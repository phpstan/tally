<?php declare(strict_types = 1);

namespace Tally\Tests;

use Override;
use Tally\Expectation\CodeEdit;
use Tally\Expectation\ExpectationSource;
use Tally\Expectation\SourceLocation;
use Tally\Metrics;

/**
 * Expectations whose edits are given, for tests of how Tally applies the edits of many cases.
 */
final class FixedEdits implements ExpectationSource
{

	/**
	 * @param list<CodeEdit> $edits
	 */
	public function __construct(private string $file, private array $edits)
	{
	}

	#[Override]
	public function read(): ?Metrics
	{
		return null;
	}

	#[Override]
	public function file(): string
	{
		return $this->file;
	}

	#[Override]
	public function edits(string $code, Metrics $measured): array
	{
		return $this->edits;
	}

	#[Override]
	public function location(): SourceLocation
	{
		return new SourceLocation($this->file, 1);
	}

}
