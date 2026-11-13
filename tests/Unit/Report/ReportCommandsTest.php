<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Report;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Report\ReportCommands;

final class ReportCommandsTest extends TestCase
{

	public function testReproduceRunsTheWorstCase(): void
	{
		$worst = Comparisons::compare("App\\ABench::benchSlower@it's", 1, ['instructions' => 1], ['instructions' => 2], null);

		$this->assertSame("vendor/bin/tally run --filter='App\\ABench::benchSlower@it'\\''s'", (new ReportCommands())->reproduce($worst));
		$this->assertSame('', (new ReportCommands())->reproduce(null));
	}

	/**
	 * @return iterable<string, array{array<string, string>, string|null, string}>
	 */
	public static function dataAccept(): iterable
	{
		$github = ['GITHUB_ACTIONS' => 'true', 'GITHUB_RUN_ID' => '42', 'GITHUB_REPOSITORY' => 'phpstan/tally'];

		yield 'GitHub Actions' => [$github, 'results', "gh run download 42 -R phpstan/tally -p 'tally-*' -D tmp/tally\nvendor/bin/tally accept tmp/tally"];
		yield 'the results of this run' => [[], 'tmp/my results', "vendor/bin/tally accept 'tmp/my results'"];
		yield 'no results' => [[], null, "vendor/bin/tally run --output=tmp/tally\nvendor/bin/tally accept tmp/tally"];
		yield 'not GitHub Actions' => [['GITHUB_ACTIONS' => 'false'] + $github, null, "vendor/bin/tally run --output=tmp/tally\nvendor/bin/tally accept tmp/tally"];
		yield 'no run ID' => [['GITHUB_ACTIONS' => 'true', 'GITHUB_REPOSITORY' => 'phpstan/tally'], 'results', "vendor/bin/tally accept 'results'"];
		yield 'no repository' => [['GITHUB_ACTIONS' => 'true', 'GITHUB_RUN_ID' => '42'], 'results', "vendor/bin/tally accept 'results'"];
	}

	/**
	 * @param array<string, string> $environment
	 */
	#[DataProvider('dataAccept')]
	public function testAccept(array $environment, ?string $resultsDirectory, string $command): void
	{
		$this->assertSame($command, (new ReportCommands())->accept($environment, $resultsDirectory));
	}

}
