<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Build;

use PHPUnit\Framework\TestCase;
use Tally\Execution\DirectoryRemover;
use function array_column;
use function getenv;
use function json_decode;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function sys_get_temp_dir;
use function uniqid;
use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

/**
 * Runs PHPStan with the dead code detector, because the provider only works inside its analysis.
 */
final class BenchmarkUsageProviderTest extends TestCase
{

	private const FIXTURES = __DIR__ . '/../../Fixtures/Build/DeadCode';

	public function testOnlyTheMethodsTallyDoesNotCallAreDead(): void
	{
		$tmpDir = sys_get_temp_dir() . '/' . uniqid('tally-phpstan-', true);
		try {
			$process = proc_open(
				[
					PHP_BINARY,
					__DIR__ . '/../../../vendor/bin/phpstan',
					'analyse',
					'--configuration=' . self::FIXTURES . '/phpstan.neon',
					'--error-format=json',
					'--no-progress',
					'--memory-limit=-1',
					'--autoload-file=' . __DIR__ . '/../../../vendor/autoload.php',
				],
				[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
				$pipes,
				null,
				[...getenv(), 'TALLY_PHPSTAN_TMP' => $tmpDir],
			);
			self::assertNotFalse($process);
			self::assertArrayHasKey(1, $pipes);
			$output = (string) stream_get_contents($pipes[1]);
			proc_close($process);
		} finally {
			(new DirectoryRemover())->remove($tmpDir);
		}

		$result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
		self::assertIsArray($result);
		self::assertArrayHasKey('files', $result);
		self::assertIsArray($result['files']);
		$messages = [];
		foreach ($result['files'] as $file) {
			self::assertIsArray($file);
			self::assertArrayHasKey('messages', $file);
			self::assertIsArray($file['messages']);
			$messages = [...$messages, ...array_column($file['messages'], 'message')];
		}

		$this->assertSame([
			'Unused Tally\Tests\Fixtures\Build\UsageBench::benchStatic',
			'Unused Tally\Tests\Fixtures\Build\UsageBench::benchPrivate',
			'Unused Tally\Tests\Fixtures\Build\UsageBench::helper',
			'Unused Tally\Tests\Fixtures\Build\UsageHelper::benchNotInABenchClass',
		], $messages);
	}

}
