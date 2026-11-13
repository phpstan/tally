<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Execution;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Execution\CodSpeedInstallation;
use Tally\Runner\CodSpeedException;

final class CodSpeedInstallationTest extends TestCase
{

	private const BIN = __DIR__ . '/../../Fixtures/CodSpeed/bin';
	private const NOT_EXECUTABLE = __DIR__ . '/../../Fixtures/CodSpeed/not-executable';
	private const LIBRARY = __DIR__ . '/../../Fixtures/InstrumentHooks/recording.c';
	private const EXTENSIONS = ['Core', 'FFI', 'posix'];

	/**
	 * @throws CodSpeedException
	 */
	public function testFindsTheFirstExecutableCodSpeedOnThePath(): void
	{
		$this->assertSame(
			[self::BIN . '/codspeed', self::LIBRARY],
			(new CodSpeedInstallation())->locate(['PATH' => '::' . self::NOT_EXECUTABLE . ':/nonexistent:' . self::BIN . ':' . self::NOT_EXECUTABLE, 'INSTRUMENT_HOOKS_LIBRARY' => self::LIBRARY], self::EXTENSIONS),
		);
	}

	/**
	 * @return iterable<string, array{array<string, string>, list<string>, string}>
	 */
	public static function dataMissing(): iterable
	{
		$environment = ['PATH' => self::BIN, 'INSTRUMENT_HOOKS_LIBRARY' => self::LIBRARY];
		$seeSetUp = "\nSet it up as https://github.com/phpstan/tally#setting-up-codspeed describes.";

		yield 'FFI' => [$environment, ['Core', 'posix'], "Tally needs PHP's FFI extension." . $seeSetUp];
		yield 'POSIX' => [$environment, ['Core', 'ffi'], "Tally needs PHP's POSIX extension." . $seeSetUp];
		yield 'no PATH' => [['INSTRUMENT_HOOKS_LIBRARY' => self::LIBRARY], self::EXTENSIONS, "Tally counts with CodSpeed's CPU simulation, but the codspeed CLI is not on PATH." . $seeSetUp];
		yield 'not on PATH' => [['PATH' => self::NOT_EXECUTABLE . ':' . __DIR__] + $environment, self::EXTENSIONS, 'the codspeed CLI is not on PATH.'];
		yield 'a directory on PATH' => [['PATH' => self::BIN . '/..'] + $environment, self::EXTENSIONS, 'the codspeed CLI is not on PATH.'];
		yield 'no library' => [['PATH' => self::BIN], self::EXTENSIONS, "Tally counts with CodSpeed's CPU simulation, but INSTRUMENT_HOOKS_LIBRARY is not set to CodSpeed's instrument-hooks library." . $seeSetUp];
		yield 'empty library' => [['INSTRUMENT_HOOKS_LIBRARY' => ''] + $environment, self::EXTENSIONS, 'INSTRUMENT_HOOKS_LIBRARY is not set'];
		yield 'missing library' => [['INSTRUMENT_HOOKS_LIBRARY' => '/nonexistent.so'] + $environment, self::EXTENSIONS, 'INSTRUMENT_HOOKS_LIBRARY is set to /nonexistent.so, which does not exist.' . $seeSetUp];
		yield 'a directory as the library' => [['INSTRUMENT_HOOKS_LIBRARY' => __DIR__] + $environment, self::EXTENSIONS, 'which does not exist.'];
	}

	/**
	 * @param array<string, string> $environment
	 * @param list<string> $extensions
	 * @throws CodSpeedException
	 */
	#[DataProvider('dataMissing')]
	public function testMissing(array $environment, array $extensions, string $message): void
	{
		$this->expectException(CodSpeedException::class);
		$this->expectExceptionMessage($message);

		(new CodSpeedInstallation())->locate($environment, $extensions);
	}

}
