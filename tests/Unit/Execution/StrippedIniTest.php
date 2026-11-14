<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Execution;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Execution\StrippedIni;

final class StrippedIniTest extends TestCase
{

	/**
	 * @return iterable<string, array{list<string>, list<string>, string|null}>
	 */
	public static function dataCompose(): iterable
	{
		yield 'the line that loads a stripped extension is commented out' => [
			["extension=intl\nzend_extension=xdebug.so\nmemory_limit=1G\n"],
			['xdebug'],
			"extension=intl\n;zend_extension=xdebug.so\nmemory_limit=1G\n\n",
		];
		yield 'a path, quotes, spaces, and other letter case' => [
			["  Zend_Extension = \"/usr/lib/php/20240924/Xdebug.so\"\nextension = '/opt/pcov.so' ; coverage\n"],
			['xdebug', 'pcov'],
			";  Zend_Extension = \"/usr/lib/php/20240924/Xdebug.so\"\n;extension = '/opt/pcov.so' ; coverage\n\n",
		];
		yield 'a file name that goes on with something other than a letter, digit, or underscore' => [
			["extension=/usr/lib/tideways/tideways-php-8.5.so\n"],
			['tideways'],
			";extension=/usr/lib/tideways/tideways-php-8.5.so\n\n",
		];
		yield 'blank lines before the line' => [
			["\n\nextension=pcov"],
			['pcov'],
			"\n\n;extension=pcov\n",
		];
		yield 'every file, each up to its first section for a path or a host, which the CLI never applies' => [
			["precision=10\n[PATH=/srv/www]\nextension=xdebug\n", "extension=pcov\n  [host=example.com]\nprecision=12\n", "precision=14\n"],
			['xdebug', 'pcov'],
			"precision=10\n\n;extension=pcov\n\nprecision=14\n\n",
		];
		yield 'no file loads a stripped extension' => [["extension=intl\n", "memory_limit=1G\n"], ['xdebug'], null];
		yield 'no stripped extensions' => [["extension=xdebug\n"], [], null];
		yield 'an extension whose name starts with that of a stripped one' => [["extension=tideways_xhprof.so\n"], ['tideways'], null];
		yield 'a directory named after a stripped extension' => [["extension=/opt/pcov/intl.so\n"], ['pcov'], null];
		yield 'a line that is commented out already, and a setting of the extension' => [[";extension=xdebug\nxdebug.mode=debug\n"], ['xdebug'], null];
	}

	/**
	 * @param list<string> $files
	 * @param list<string> $extensions
	 */
	#[DataProvider('dataCompose')]
	public function testCompose(array $files, array $extensions, ?string $expected): void
	{
		$this->assertSame($expected, (new StrippedIni())->compose($files, $extensions));
	}

}
