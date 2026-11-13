<?php declare(strict_types = 1);

use Tally\Configuration;

return Configuration::create()
	->withPaths([__DIR__])
	->withIniEntries(['memory_limit=64M'])
	->withTimeLimits(1, 60);
