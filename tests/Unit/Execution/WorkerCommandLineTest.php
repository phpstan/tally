<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Execution;

use PHPUnit\Framework\TestCase;
use Tally\CaseId;
use Tally\Execution\WorkerCommandLine;
use Tally\Execution\WorkerSettings;

final class WorkerCommandLineTest extends TestCase
{

	public function testNative(): void
	{
		$this->assertSame([
			'/usr/bin/php',
			'-d',
			'memory_limit=-1',
			'-d',
			'opcache.enable_cli=1',
			'vendor/bin/tally',
			'worker',
			'--case=App\FooBench::benchFoo@it\'s',
			'--scratch=/work/scratch',
			'--result=/work/native.json',
			'--config=tally.php',
			'--',
			'benchmarks',
		], self::commandLine()->native(self::settings(), self::id(), '/work/scratch', '/work/native.json'));
	}

	public function testCountedRunsTheWorkerUnderCallgrindAsCodSpeedDoes(): void
	{
		$this->assertSame([
			'/opt/codspeed/valgrind',
			'-q',
			'--tool=callgrind',
			'--cache-sim=yes',
			'--I1=32768,8,64',
			'--D1=32768,8,64',
			'--LL=8388608,16,64',
			'--cycle-estimation=yes',
			'--deterministic-rng=yes',
			'--collect-systime=nsec',
			'--read-inline-info=yes',
			'--instr-atstart=no',
			'--separate-threads=no',
			'--compress-strings=no',
			'--combine-dumps=yes',
			'--dump-line=no',
			'--callgrind-out-file=/work/profile/%p.out',
			'/usr/bin/php',
			'-d',
			'ffi.enable=1',
			'-d',
			'zend.max_allowed_stack_size=-1',
			'-d',
			'memory_limit=-1',
			'-d',
			'opcache.enable_cli=1',
			'vendor/bin/tally',
			'worker',
			'--case=App\FooBench::benchFoo@it\'s',
			'--scratch=/work/scratch',
			'--result=/work/counted.json',
			'--instrument-hooks=/opt/codspeed/libinstrument_hooks.so',
			'--config=tally.php',
			'--',
			'benchmarks',
		], self::commandLine()->counted(self::settings(), self::id(), '/work/scratch', '/work/counted.json', '/work/profile'));
	}

	private static function commandLine(): WorkerCommandLine
	{
		return new WorkerCommandLine('/usr/bin/php', 'vendor/bin/tally');
	}

	private static function settings(): WorkerSettings
	{
		return new WorkerSettings(
			['memory_limit=-1', 'opcache.enable_cli=1'],
			['--config=tally.php', '--', 'benchmarks'],
			'/opt/codspeed/valgrind',
			'/opt/codspeed/libinstrument_hooks.so',
			'/work',
			60,
			1800,
		);
	}

	private static function id(): CaseId
	{
		return new CaseId('App\FooBench', 'benchFoo', "it's");
	}

}
