<?php declare(strict_types = 1);

namespace Tally\Bench\Result;

use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;
use Tally\Attribute\Warmup;
use Tally\Result\CallgrindProfile;
use function sprintf;

/**
 * tally run reads the profile CodSpeed writes for every case. A profile has a line for each
 * function and each call between functions the case ran, so profiles of large cases, like
 * PHPStan analysing a file, are megabytes long.
 */
#[Warmup(1)]
final class CallgrindProfileBench
{

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function profiles(): iterable
	{
		foreach ([100, 10_000] as $functions) {
			yield sprintf('functions-%d', $functions) => [self::profile($functions)];
		}
	}

	#[DataProvider('profiles')]
	#[ExpectedFor('functions-100', instructions: '747.9K', cycles: '799.4K', memory: '64.41KiB')]
	#[ExpectedFor('functions-10000', instructions: '53.12M', cycles: '56.83M', memory: '4.069MiB')]
	public function benchParts(string $profile): void
	{
		(new CallgrindProfile())->parts($profile);
	}

	/**
	 * A profile like tally_hooks dumps for a case: the #warmup and #baseline parts, which
	 * call few functions, and the part of the case, which calls the given number of functions.
	 */
	private static function profile(int $functions): string
	{
		$profile = "# callgrind format\nversion: 1\ncreator: callgrind-3.26.0.codspeed13\npid: 1000\ncmd: php bin/tally worker\n";
		foreach (['#warmup' => 10, '#baseline' => 10, '' => $functions] as $suffix => $count) {
			$profile .= sprintf("part: %d\n", $count);
			$profile .= "desc: I1 cache: 32768 B, 64 B, 8-way associative\ndesc: D1 cache: 32768 B, 64 B, 8-way associative\ndesc: LL cache: 8388608 B, 64 B, 16-way associative\n\n";
			$profile .= sprintf("desc: Trigger: Client Request: Generated\\SuiteBench::benchMethod%s\n\n", $suffix);
			$profile .= "positions: line\nevents: Ir Dr Dw I1mr D1mr D1mw ILmr DLmr DLmw sysCount sysTime sysCpuTime Ct Cl\n\nob=/usr/bin/php8.5\nfl=???\n";
			for ($i = 0; $i < $count; $i++) {
				$profile .= sprintf("fn=zend_function_%d\n0 %d %d %d 1 2 0 1 2 0 0 0 0 %d %d\n", $i, 400 + $i, 150, 120, 30_000 + $i, 85_000 + $i);
				$profile .= sprintf("cfn=zend_function_%d\ncalls=%d 0\n0 606 114 0 5 3 0 5 3 0 0 0 0 31823 61012\n\n", $i + 1, 1 + $i % 7);
			}
			$profile .= sprintf("totals: %d 2968 1219 289 574 59 289 573 58 0 0 0 499711 2065668\n\n", 1000 * $count);
		}

		return $profile;
	}

}
