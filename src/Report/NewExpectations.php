<?php declare(strict_types = 1);

namespace Tally\Report;

/**
 * The expected numbers of the new cases as a patch, and the cases whose expectations cannot be
 * added, with why.
 */
final class NewExpectations
{

	public const TITLE = 'Expected numbers of the new benchmarks';

	public const TEXT = 'Apply this patch with git apply, or accept the new numbers as above.';

	/**
	 * @param array<string, string> $failures the reasons by case ID
	 */
	public function __construct(
		public readonly string $patch,
		public readonly array $failures
	)
	{
	}

	public static function none(): self
	{
		return new self('', []);
	}

	public function isEmpty(): bool
	{
		return $this->patch === '' && $this->failures === [];
	}

}
