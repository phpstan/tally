<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Execution;

use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tally\Execution\DirectoryRemover;
use Tally\Execution\StrippedIni;
use Tally\Execution\StrippedIniFile;
use function file_put_contents;
use function getmypid;
use function mkdir;
use function sys_get_temp_dir;

final class StrippedIniFileTest extends TestCase
{

	private string $directory;

	#[Override]
	protected function setUp(): void
	{
		$this->directory = sys_get_temp_dir() . '/tally-ini-' . getmypid();
		mkdir($this->directory . '/conf.d', 0777, true);
		file_put_contents($this->directory . '/php.ini', "memory_limit=1G\n");
		file_put_contents($this->directory . '/conf.d/20-xdebug.ini', "zend_extension=xdebug.so\n");
	}

	#[Override]
	protected function tearDown(): void
	{
		(new DirectoryRemover())->remove($this->directory);
	}

	public function testWritesTheFilesWithoutTheStrippedExtensions(): void
	{
		$work = $this->directory . '/work/tally';

		$file = $this->iniFile()->write(['xdebug'], $work);

		$this->assertSame($work . '/php.ini', $file);
		$this->assertStringEqualsFile($work . '/php.ini', "memory_limit=1G\n\n;zend_extension=xdebug.so\n\n");
	}

	public function testWritesNothingWhenNoFileLoadsAStrippedExtension(): void
	{
		$work = $this->directory . '/work';

		$this->assertNull($this->iniFile()->write(['pcov'], $work));
		$this->assertDirectoryDoesNotExist($work);
	}

	public function testReportsAFileThatCannotBeWritten(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Cannot write /nonexistent/tally/php.ini.');

		$this->iniFile()->write(['xdebug'], '/nonexistent/tally');
	}

	private function iniFile(): StrippedIniFile
	{
		return new StrippedIniFile(new StrippedIni(), [$this->directory . '/php.ini', $this->directory . '/conf.d/20-xdebug.ini']);
	}

}
