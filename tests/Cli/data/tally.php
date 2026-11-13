<?php declare(strict_types = 1);

use PHPStan\Tally\Configuration;

return Configuration::create()
	->withPaths([__DIR__ . '/../../Discovery/data/suite']);
