<?php declare(strict_types = 1);

namespace Tally\Selection;

use InvalidArgumentException;
use Tally\BenchmarkCase;
use function array_filter;
use function array_values;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtolower;

/**
 * Keeps the cases whose ID contains the filter, ignoring case, or matches it when the filter is
 * a regular expression between slashes, like /@size-10$/.
 */
final class FilterSelector implements CaseSelector
{

	private ?string $pattern = null;

	/**
	 * @throws InvalidArgumentException
	 */
	public function __construct(private string $filter)
	{
		if (strlen($filter) < 2 || !str_starts_with($filter, '/') || !str_ends_with($filter, '/')) {
			return;
		}

		if (@preg_match($filter, '') === false) {
			throw new InvalidArgumentException(sprintf('Filter %s is not a valid regular expression.', $filter));
		}
		$this->pattern = $filter;
	}

	public function select(array $cases): array
	{
		return array_values(array_filter($cases, function (BenchmarkCase $case): bool {
			$id = $case->id->toString();
			if ($this->pattern !== null) {
				return preg_match($this->pattern, $id) === 1;
			}

			return str_contains(strtolower($id), strtolower($this->filter));
		}));
	}

}
