<?php declare(strict_types = 1);

use Tally\Configuration;

// the fake Valgrind reads the counts from TALLY_FAKE_COUNTS
return Configuration::create()
	->withEnvironmentVariables(['PATH', 'PHP_INI_SCAN_DIR', 'TALLY_FAKE_COUNTS']);
