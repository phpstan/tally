<?php declare(strict_types = 1);

namespace Tally\Selection;

use InvalidArgumentException;
use Override;
use Tally\BenchmarkCase;
use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtolower;

/**
 * Keeps the cases whose ID contains the filter, ignoring case, or matches it when the filter is
 * a regular expression between slashes, like /@size-10$/, and the bases of the cases compared by
 * their growth among them.
 */
final class FilterSelector implements CaseSelector
{

	/** The filter when it is a regular expression */
	private ?string $pattern;

	/**
	 * @throws InvalidArgumentException
	 */
	public function __construct(private string $filter)
	{
		$this->pattern = $this->pattern($filter);
	}

	#[Override]
	public function select(array $cases): array
	{
		$matching = array_filter($cases, $this->matches(...));
		// the cases compared by their growth need the measurement of their base
		$bases = array_map(static fn (BenchmarkCase $case): ?string => $case->growth?->base->toString(), $matching);

		return array_values(array_filter($cases, static fn (BenchmarkCase $case): bool => in_array($case, $matching, true) || in_array($case->id->toString(), $bases, true)));
	}

	private function matches(BenchmarkCase $case): bool
	{
		$id = $case->id->toString();
		if ($this->pattern !== null) {
			return preg_match($this->pattern, $id) === 1;
		}

		return str_contains(strtolower($id), strtolower($this->filter));
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private function pattern(string $filter): ?string
	{
		if (strlen($filter) < 2 || !str_starts_with($filter, '/') || !str_ends_with($filter, '/')) {
			return null;
		}
		if (@preg_match($filter, '') === false) {
			throw new InvalidArgumentException(sprintf('Filter %s is not a valid regular expression.', $filter));
		}

		return $filter;
	}

}
