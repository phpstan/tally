<?php declare(strict_types = 1);

use Tally\Configuration;

// the recording build of tally_hooks, which the tests load from an ini file
return Configuration::create()
	->withPaths([__DIR__])
	->withEnvironmentVariables(['PATH', 'PHP_INI_SCAN_DIR'])
	->withStrippedExtensions(['not_an_extension', 'TALLY_HOOKS']);
