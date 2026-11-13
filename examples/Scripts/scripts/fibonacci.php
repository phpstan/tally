<?php declare(strict_types = 1);

/*
 * instructions: 679.5K
 * cycles: 854.8K
 * memory: 36.30KiB
 */

$previous = 0;
$current = 1;
for ($i = 0; $i < 1_000; $i++) {
	[$previous, $current] = [$current, ($previous + $current) % 1_000_000_007];
}

return $current;
