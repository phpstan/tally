<?php declare(strict_types = 1);

use Tally\Configuration;

return Configuration::create()
	->withPaths([__DIR__])
	->withEnvironmentVariables(['PATH', 'PHP_INI_SCAN_DIR', 'TALLY_EXAMPLE']);
