<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Result;

use PHPUnit\Framework\TestCase;
use Tally\Metrics;
use Tally\Result\CallgrindProfile;
use function array_map;

final class CallgrindProfileTest extends TestCase
{

	private const PROFILE = <<<'CALLGRIND'
		# callgrind format
		version: 1
		creator: callgrind-3.26.0.codspeed12
		pid: 34
		cmd:  /usr/bin/php8.5 -d ffi.enable=1 bin/tally worker
		part: 1
		desc: Spawned pid: 35

		desc: I1 cache: 32768 B, 64 B, 8-way associative
		desc: Timerange: Basic block 0 - 0
		desc: Trigger: Client Request: Metadata: tally 1.0.0

		positions: line
		events: Ir Dr Dw I1mr D1mr D1mw ILmr DLmr DLmw sysCount sysTime sysCpuTime Ct Cl
		summary: 999

		totals: 999 1 1 1 1 1 1 1 1 1 1 1 1 1

		part: 2
		desc: Benchmark pid: 34
		desc: Trigger: Client Request: App\FooBench::benchFoo@a#baseline

		events: Ir Dr Dw I1mr D1mr D1mw ILmr DLmr DLmw sysCount sysTime sysCpuTime Ct Cl
		totals: 5805 1898 743 245 479 39 245 472 38 0 0 0 323149 1444788

		part: 3
		desc: Benchmark pid: 34

		desc: Timerange: Basic block 0 - 1134541461
		desc: Trigger: Client Request: App\FooBench::benchFoo@a

		positions: line
		events: Ir Dr Dw I1mr D1mr D1mw ILmr DLmr DLmw sysCount sysTime sysCpuTime Ct Cl
		summary: 0

		fn=zend_execute
		0 10 6 1 3 2 1 3 2 1 0 0 0 591 2636

		totals: 5605163255 1529261174 892831611 32176288 34788426 12883708 89670 1339330 2320891 41472 1274091615 393413506 343745161500 1032710357354

		part: 4
		desc: Timerange: Basic block 1134541461 - 1134541462
		desc: Trigger: Program termination
		events: Ir Dr Dw I1mr D1mr D1mw ILmr DLmr DLmw sysCount sysTime sysCpuTime Ct Cl
		summary: 7

		totals: 7 1 1 1 1 1 1 1 1 1 1 1 1 1
		CALLGRIND;

	public function testReadsTheInstructionsAndEstimatedCyclesOfEveryNamedPart(): void
	{
		$this->assertSame([
			'Metadata: tally 1.0.0' => ['instructions' => 999, 'cycles' => 999 + 10 * 3 + 100 * 3],
			// 5805 + 10 * (245 + 479 + 39) + 100 * (245 + 472 + 38)
			'App\FooBench::benchFoo@a#baseline' => ['instructions' => 5805, 'cycles' => 88_935],
			// 5605163255 + 10 * (32176288 + 34788426 + 12883708) + 100 * (89670 + 1339330 + 2320891)
			'App\FooBench::benchFoo@a' => ['instructions' => 5_605_163_255, 'cycles' => 6_778_636_575],
		], self::parts(self::PROFILE));
	}

	public function testAProcessWithoutRequestsHasNoParts(): void
	{
		$this->assertSame([], self::parts(<<<'CALLGRIND'
			# callgrind format
			cmd:  /usr/bin/stty -a
			part: 1
			desc: Trigger: Program termination
			events: Ir Dr Dw I1mr D1mr D1mw ILmr DLmr DLmw
			totals: 21618 5618 2229 497 177 70 491 177 70
			CALLGRIND));
	}

	public function testEventsMissingFromTheProfileCountAsZero(): void
	{
		$this->assertSame(['part' => ['instructions' => 100, 'cycles' => 100 + 10 * 2 + 100 * 3]], self::parts(<<<'CALLGRIND'
			events: Ir D1mr DLmw
			desc: Trigger: Client Request: part
			totals: 100 2 3
			CALLGRIND));
	}

	public function testAProfileWithoutInstructionsCountsOnlyTheMisses(): void
	{
		$this->assertSame(['part' => ['instructions' => 0, 'cycles' => 10 * 2 + 100 * 3]], self::parts(<<<'CALLGRIND'
			events: D1mr DLmw
			desc: Trigger: Client Request: part
			totals: 2 3
			CALLGRIND));
	}

	public function testTheSpaceAfterALabelIsOptional(): void
	{
		$this->assertSame(['part' => ['instructions' => 4, 'cycles' => 14]], self::parts(<<<'CALLGRIND'
			events:Ir I1mr
			desc: Trigger: Client Request: part
			totals: 4 1
			CALLGRIND));
	}

	public function testTotalsThatDoNotMatchTheEventsAreSkipped(): void
	{
		$this->assertSame(['good' => ['instructions' => 5, 'cycles' => 5]], self::parts(<<<'CALLGRIND'
			events: Ir I1mr
			desc: Trigger: Client Request: broken
			totals: 100
			part: 2
			desc: Trigger: Client Request: good
			totals: 5 0
			CALLGRIND));
	}

	public function testTotalsOfAPartWithoutAName(): void
	{
		$this->assertSame(['named' => ['instructions' => 2, 'cycles' => 2]], self::parts(<<<'CALLGRIND'
			events: Ir
			part: 1
			totals: 1
			part: 2
			desc: Trigger: Client Request: named
			totals: 2
			part: 3
			totals: 3
			CALLGRIND));
	}

	/**
	 * @return array<string, array<string, int>>
	 */
	private static function parts(string $profile): array
	{
		return array_map(static fn (Metrics $metrics): array => $metrics->toArray(), (new CallgrindProfile())->parts($profile));
	}

}
