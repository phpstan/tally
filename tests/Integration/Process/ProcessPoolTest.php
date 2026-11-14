<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Process;

use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tally\Process\Job;
use Tally\Process\ProcessCommand;
use Tally\Process\ProcessKiller;
use Tally\Process\ProcessPool;
use Tally\Process\ProcessResult;
use Tally\Process\ProcessTable;
use function array_map;
use function file_get_contents;
use function getmypid;
use function implode;
use function is_dir;
use function mkdir;
use function posix_kill;
use function sprintf;
use function sys_get_temp_dir;
use function usleep;
use const PHP_BINARY;

final class ProcessPoolTest extends TestCase
{

	private const TIME_LIMIT = 30;

	private string $directory;

	#[Override]
	protected function setUp(): void
	{
		$this->directory = sys_get_temp_dir() . '/tally-pool-' . getmypid();
		if (is_dir($this->directory)) {
			return;
		}

		mkdir($this->directory);
	}

	public function testRunsTheStepsOfAJobWhileEachSucceeds(): void
	{
		$job = new Job('job', [
			$this->php('echo "first\n"; fwrite(STDERR, "error\n");', 'first'),
			$this->php('exit(3);', 'second'),
			$this->php('echo "third\n";', 'third'),
		]);

		$finished = $this->runJobs([$job], 1, []);

		$this->assertSame(['job: 0, 3'], $finished);
		$this->assertSame("first\nerror\n", file_get_contents($this->directory . '/first.log'));
	}

	public function testReportsJobsInTheOrderTheyFinish(): void
	{
		$slow = new Job('slow', [$this->php('usleep(300_000);', 'slow')]);
		$fast = new Job('fast', [$this->php('', 'fast')]);

		$this->assertSame(['fast: 0', 'slow: 0'], $this->runJobs([$slow, $fast], 2, []));
		$this->assertSame(['slow: 0', 'fast: 0'], $this->runJobs([$slow, $fast], 1, []));
	}

	public function testPassesTheEnvironment(): void
	{
		$job = new Job('job', [$this->php('echo getenv("TALLY_TEST"), "\n", var_export(getenv("HOME"), true);', 'environment')]);

		$this->runJobs([$job], 1, ['TALLY_TEST' => 'passed']);

		$this->assertSame("passed\nfalse", file_get_contents($this->directory . '/environment.log'));
	}

	public function testReportsTheSignalThatKilledAProcess(): void
	{
		$job = new Job('job', [$this->php('posix_kill(getmypid(), SIGTERM); sleep(5);', 'signal')]);

		$this->assertSame(['job: 143'], $this->runJobs([$job], 1, []));
	}

	public function testKillsAStepThatRunsTooLongWithItsDescendants(): void
	{
		$pidFile = $this->directory . '/grandchild.pid';
		// the grandchild ignores the signals next to SIGKILL, so killing it with another one fails
		$code = '$child = proc_open(["sh", "-c", "trap \'\' FPE USR1; echo \$\$ > ' . $pidFile . '; exec sleep 30"], [], $pipes); sleep(30);';
		$job = new Job('job', [new ProcessCommand([PHP_BINARY, '-r', $code], $this->directory . '/slow.log', 1), $this->php('', 'never')]);

		$this->assertSame(['job: timed out'], $this->runJobs([$job], 1, []));

		$grandchild = (int) file_get_contents($pidFile);
		$this->assertGreaterThan(0, $grandchild);
		// the kill is asynchronous, give the kernel a moment
		for ($i = 0; $i < 100 && posix_kill($grandchild, 0); $i++) {
			usleep(10_000);
		}
		$this->assertFalse(posix_kill($grandchild, 0));
	}

	public function testReportsAProcessThatCannotBeStarted(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(sprintf('Cannot start %s with its output in /nonexistent/job.log.', PHP_BINARY));

		$this->runJobs([new Job('job', [new ProcessCommand([PHP_BINARY, '-r', ''], '/nonexistent/job.log', self::TIME_LIMIT)])], 1, []);
	}

	private function php(string $code, string $name): ProcessCommand
	{
		return new ProcessCommand([PHP_BINARY, '-r', $code], $this->directory . '/' . $name . '.log', self::TIME_LIMIT);
	}

	/**
	 * @param list<Job<string>> $jobs
	 * @param positive-int $parallel
	 * @param array<string, string> $environment
	 * @return list<string>
	 */
	private function runJobs(array $jobs, int $parallel, array $environment): array
	{
		$finished = [];
		(new ProcessPool(new ProcessKiller(new ProcessTable())))->run($jobs, $parallel, $this->directory, $environment, static function (Job $job, array $results) use (&$finished): void {
			$finished[] = $job->subject . ': ' . implode(', ', array_map(static fn (ProcessResult $result): string => $result->timedOut ? 'timed out' : (string) $result->exitCode, $results));
		});

		return $finished;
	}

}
