<?php declare(strict_types = 1);

use Tally\Configuration;

// for workers in the test process, which loads PCOV to collect coverage
return Configuration::create()
	->withPaths([__DIR__])
	->withEnvironmentVariables(['PATH', 'PHP_INI_SCAN_DIR'])
	->withStrippedExtensions([]);
