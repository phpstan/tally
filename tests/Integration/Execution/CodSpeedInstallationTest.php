<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Execution;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Execution\CodSpeedInstallation;
use Tally\Execution\DirectoryRemover;
use Tally\Runner\CodSpeedException;
use Tally\Tests\InstrumentHooksFixture;
use function chmod;
use function clearstatcache;
use function copy;
use function fileperms;
use function getmypid;
use function mkdir;
use function sys_get_temp_dir;

final class CodSpeedInstallationTest extends TestCase
{

	private const EXTENSIONS = ['Core', 'FFI', 'posix'];

	/**
	 * @throws CodSpeedException
	 */
	public function testFindsTheBuildForThisPlatform(): void
	{
		$build = InstrumentHooksFixture::codspeed();

		$this->assertSame(
			[$build . '/valgrind', $build . '/libexec', $build . '/libinstrument_hooks.so'],
			(new CodSpeedInstallation($build))->locate(self::EXTENSIONS),
		);
	}

	/**
	 * @throws CodSpeedException
	 */
	public function testMakesTheLauncherAndTheToolsExecutable(): void
	{
		$build = sys_get_temp_dir() . '/tally-installation-' . getmypid() . '/linux-x86_64';
		mkdir($build . '/libexec', 0777, true);
		foreach (['valgrind', 'libexec/callgrind-amd64-linux', 'libexec/default.supp'] as $file) {
			copy(__FILE__, $build . '/' . $file);
			chmod($build . '/' . $file, 0644);
		}

		try {
			(new CodSpeedInstallation($build))->locate(self::EXTENSIONS);
			// PHP 8.2 keeps the permissions it read before the chmod
			clearstatcache();

			$this->assertSame(0755, fileperms($build . '/valgrind') & 0777);
			$this->assertSame(0755, fileperms($build . '/libexec/callgrind-amd64-linux') & 0777);
			$this->assertSame(0644, fileperms($build . '/libexec/default.supp') & 0777);
		} finally {
			(new DirectoryRemover())->remove($build . '/..');
		}
	}

	/**
	 * @return iterable<string, array{string, list<string>, string}>
	 */
	public static function dataMissing(): iterable
	{
		$build = InstrumentHooksFixture::codspeed();
		$seeSetUp = "\nSet it up as https://github.com/phpstan/tally#installation describes.";

		yield 'FFI' => [$build, ['Core', 'posix'], "Tally needs PHP's FFI extension." . $seeSetUp];
		yield 'POSIX' => [$build, ['Core', 'ffi'], "Tally needs PHP's POSIX extension." . $seeSetUp];
		yield 'no build for the platform' => [
			'/nonexistent/codspeed/linux-riscv64',
			self::EXTENSIONS,
			"Tally counts with CodSpeed's Valgrind, but has no build of it for linux-riscv64 in /nonexistent/codspeed.\n"
			. 'Releases of Tally include builds for linux-x86_64 and linux-aarch64. In a clone of Tally, build it with build/build-codspeed.sh.',
		];
		yield 'a directory without the launcher' => [__DIR__, self::EXTENSIONS, 'but has no build of it for Execution in'];
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

		(new CodSpeedInstallation($build))->locate($extensions);
	}

}
