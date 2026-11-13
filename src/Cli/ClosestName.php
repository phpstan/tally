<?php declare(strict_types = 1);

namespace Tally\Cli;

use function levenshtein;
use function strtolower;

/** The name a mistyped command or option most likely meant, for a "did you mean" suggestion */
final class ClosestName
{

	/** How many letters a name may differ by to be suggested */
	private const DISTANCE_LIMIT = 2;

	/**
	 * @param list<string> $candidates
	 * @return string|null null when no candidate is close enough
	 */
	public function find(string $name, array $candidates): ?string
	{
		$closest = null;
		$closestDistance = self::DISTANCE_LIMIT + 1;
		foreach ($candidates as $candidate) {
			$distance = levenshtein(strtolower($name), strtolower($candidate));
			if ($distance >= $closestDistance) {
				continue;
			}

			$closest = $candidate;
			$closestDistance = $distance;
		}

		return $closest;
	}

}
