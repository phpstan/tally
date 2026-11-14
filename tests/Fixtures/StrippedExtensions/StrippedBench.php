<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\StrippedExtensions;

use RuntimeException;
use function extension_loaded;
use function php_ini_loaded_file;
use function php_ini_scanned_files;
use function sprintf;
use function var_export;

final class StrippedBench
{

	public function benchExtensions(): void
	{
		throw new RuntimeException(sprintf(
			'tally_hooks is %s, the php.ini is %s, PHP scans %s.',
			extension_loaded('tally_hooks') ? 'loaded' : 'not loaded',
			var_export(php_ini_loaded_file(), true),
			php_ini_scanned_files() === false ? 'no files' : 'files',
		));
	}

}
