<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Execution;

use PHPUnit\Framework\TestCase;
use Tally\Attribute\RelativeTolerance;
use Tally\BenchmarkCase;
use Tally\CaseId;
use Tally\Execution\WorkerCommandLine;
use Tally\Execution\WorkerSettings;
use Tally\Tests\InMemoryExpectations;
use function array_slice;

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
			'/project/benchmarks/FooBench.php',
		], self::commandLine()->native(self::settings('/opt/codspeed/tally_hooks-8.5.so'), self::case(), '/work/scratch', '/work/native.json'));
	}

	public function testCountedRunsTheWorkerUnderCallgrindWithTheCachesOfCodSpeed(): void
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
			'--collect-atstart=no',
			'--separate-threads=no',
			'--compress-strings=no',
			'--combine-dumps=yes',
			'--dump-line=no',
			'--callgrind-out-file=/work/profile/%p.out',
			'/usr/bin/php',
			'-d',
			'zend.max_allowed_stack_size=-1',
			'-d',
			'extension=/opt/codspeed/tally_hooks-8.5.so',
			'-d',
			'memory_limit=-1',
			'-d',
			'opcache.enable_cli=1',
			'vendor/bin/tally',
			'worker',
			'--case=App\FooBench::benchFoo@it\'s',
			'--scratch=/work/scratch',
			'--result=/work/counted.json',
			'--counted',
			'--config=tally.php',
			'--',
			'/project/benchmarks/FooBench.php',
		], self::commandLine()->counted(self::settings('/opt/codspeed/tally_hooks-8.5.so'), self::case(), '/work/scratch', '/work/counted.json', '/work/profile'));
	}

	private static function commandLine(): WorkerCommandLine
	{
		return new WorkerCommandLine('/usr/bin/php', 'vendor/bin/tally');
	}

	public function testCountedWithoutLoadingTheExtensionPhpLoadsAlready(): void
	{
		$this->assertSame(
			['-d', 'zend.max_allowed_stack_size=-1', '-d', 'memory_limit=-1', '-d', 'opcache.enable_cli=1', 'vendor/bin/tally'],
			array_slice(self::commandLine()->counted(self::settings(null), self::case(), '/work/scratch', '/work/counted.json', '/work/profile'), 19, 7),
		);
	}

	private static function settings(?string $extension): WorkerSettings
	{
		return new WorkerSettings(
			['memory_limit=-1', 'opcache.enable_cli=1'],
			['--config=tally.php'],
			'/opt/codspeed/valgrind',
			$extension,
			'/work',
			60,
			1800,
		);
	}

	private static function case(): BenchmarkCase
	{
		return new BenchmarkCase(
			new CaseId('App\FooBench', 'benchFoo', "it's"),
			'/project/benchmarks/FooBench.php',
			[],
			0,
			[],
			[],
			new InMemoryExpectations(null, '/project/benchmarks/FooBench.php'),
			new RelativeTolerance(0.1),
			null,
		);
	}

}
