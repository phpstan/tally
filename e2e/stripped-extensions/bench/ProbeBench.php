<?php declare(strict_types = 1);

namespace Tally\E2E\StrippedExtensions;

use RuntimeException;
use function array_intersect;
use function get_loaded_extensions;
use function implode;

final class ProbeBench
{

	public function benchSort(): void
	{
		$loaded = array_intersect(['xdebug', 'pcov'], get_loaded_extensions());
		if ($loaded !== []) {
			throw new RuntimeException('The case runs with ' . implode(', ', $loaded) . '.');
		}

		// PHP code, which an enabled debugger or coverage driver slows down, unlike the C of PHP's functions
		$sum = 0;
		for ($i = 0; $i < 100_000; $i++) {
			$sum += $this->step($i);
		}
	}

	private function step(int $i): int
	{
		return $i % 7;
	}

}
