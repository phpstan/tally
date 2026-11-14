<?php declare(strict_types = 1);

namespace Tally\Execution;

use function preg_quote;
use function preg_replace;

/**
 * The php.ini of the workers when PHP's ini files load extensions that the cases run without, such
 * as Xdebug or PCOV: every file, with the lines that load those extensions commented out. PHP often
 * loads them enabled, which multiplies the counts, and a setting cannot unload an extension, so the
 * workers start without it.
 */
final class StrippedIni
{

	/**
	 * @param list<string> $files the contents of the ini files PHP loaded, in the order it loaded them
	 * @param list<string> $extensions the names of the extensions to strip
	 * @return string|null null when no file loads any of the extensions
	 */
	public function compose(array $files, array $extensions): ?string
	{
		$composed = '';
		$stripped = 0;
		foreach ($files as $file) {
			// The CLI never applies what follows a [PATH=] or [HOST=] section, and joined with the
			// next file, the section would swallow that file too.
			$file = (string) preg_replace('~^[ \t]*\[(?:PATH|HOST)[ \t]*=.*~ims', '', $file);
			foreach ($extensions as $extension) {
				// the file name, not a directory, starts with the extension's name, which ends there:
				// tideways-php-8.5.so is Tideways, tideways_xhprof.so is not
				$file = (string) preg_replace(
					'~^[ \t]*(?:zend_)?extension[ \t]*=[ \t]*["\']?(?:[^"\';\n]*/)?' . preg_quote($extension, '~') . '(?![\w/])~mi',
					';$0',
					$file,
					-1,
					$count,
				);
				$stripped += $count;
			}
			$composed .= $file . "\n";
		}

		return $stripped === 0 ? null : $composed;
	}

}
