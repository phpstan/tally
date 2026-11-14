<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Execution;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Execution\CodSpeedInstallation;
use Tally\Execution\DirectoryRemover;
use Tally\Runner\CodSpeedException;
use Tally\Tests\ValgrindFixture;
use function chmod;
use function clearstatcache;
use function copy;
use function fileperms;
use function getmypid;
use function mkdir;
use function sys_get_temp_dir;

final class CodSpeedInstallationTest extends TestCase
{

	private const EXTENSIONS = ['Core', 'posix'];

	/**
	 * @throws CodSpeedException
	 */
	public function testFindsTheBuildsForThisPlatformAndPhp(): void
	{
		$build = $this->build();

		try {
			$this->assertSame(
				[$build . '/valgrind', $build . '/libexec', $build . '/tally_hooks-8.5-zts.so'],
				(new CodSpeedInstallation($build))->locate(self::EXTENSIONS, '8.5-zts'),
			);
		} finally {
			(new DirectoryRemover())->remove($build . '/..');
		}
	}

	/**
	 * @throws CodSpeedException
	 */
	public function testLeavesOutTheExtensionWhenPhpLoadsIt(): void
	{
		$build = ValgrindFixture::codspeed();

		$this->assertSame(
			[$build . '/valgrind', $build . '/libexec', null],
			(new CodSpeedInstallation($build))->locate([...self::EXTENSIONS, 'tally_hooks'], '8.5'),
		);
	}

	/**
	 * @throws CodSpeedException
	 */
	public function testMakesTheLauncherAndTheToolsExecutable(): void
	{
		$build = $this->build();

		try {
			(new CodSpeedInstallation($build))->locate(self::EXTENSIONS, '8.5-zts');
			// PHP 8.2 keeps the permissions it read before the chmod
			clearstatcache();

			$this->assertSame(0755, fileperms($build . '/valgrind') & 0777);
			$this->assertSame(0755, fileperms($build . '/libexec/callgrind-amd64-linux') & 0777);
			$this->assertSame(0644, fileperms($build . '/libexec/default.supp') & 0777);
		} finally {
			(new DirectoryRemover())->remove($build . '/..');
		}
	}

	/** A build with a launcher and a tool that are not executable, and tally_hooks for PHP 8.5 ZTS */
	private function build(): string
	{
		$build = sys_get_temp_dir() . '/tally-installation-' . getmypid() . '/linux-x86_64';
		mkdir($build . '/libexec', 0777, true);
		foreach (['valgrind', 'libexec/callgrind-amd64-linux', 'libexec/default.supp', 'tally_hooks-8.5-zts.so'] as $file) {
			copy(__FILE__, $build . '/' . $file);
			chmod($build . '/' . $file, 0644);
		}

		return $build;
	}

	/**
	 * @return iterable<string, array{string, list<string>, string}>
	 */
	public static function dataMissing(): iterable
	{
		$build = ValgrindFixture::codspeed();
		$seeSetUp = "\nSet it up as https://github.com/phpstan/tally#installation describes.";

		yield 'POSIX' => [$build, ['Core', 'tally_hooks'], "Tally needs PHP's POSIX extension." . $seeSetUp];
		yield 'no build for the platform' => [
			'/nonexistent/codspeed/linux-riscv64',
			self::EXTENSIONS,
			"Tally counts with CodSpeed's Valgrind, but has no build of it for linux-riscv64 in /nonexistent/codspeed.\n"
			. 'Releases of Tally include builds for linux-x86_64 and linux-aarch64. In a clone of Tally, build it with make codspeed.',
		];
		yield 'a directory without the launcher' => [__DIR__, self::EXTENSIONS, 'but has no build of it for Execution in'];
		yield 'no build of tally_hooks for the PHP' => [
			$build,
			self::EXTENSIONS,
			'Tally counts with its tally_hooks extension, but has no build of it for PHP 8.5-debug in ' . $build . ".\n"
			. 'Releases of Tally include builds for PHP 8.2 to 8.6, NTS and ZTS, but not debug builds. Build it from ext/tally_hooks with build/build-tally-hooks.sh, and load it in php.ini.',
		];
	}

	/**
	 * @param list<string> $extensions
	 * @throws CodSpeedException
	 */
	#[DataProvider('dataMissing')]
	public function testMissing(string $build, array $extensions, string $message): void
	{
		$this->expectException(CodSpeedException::class);
		$this->expectExceptionMessage($message);

		(new CodSpeedInstallation($build))->locate($extensions, '8.5-debug');
	}

}
