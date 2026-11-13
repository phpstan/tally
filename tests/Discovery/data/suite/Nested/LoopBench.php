<?php declare(strict_types = 1);

namespace PHPStan\Tally\Discovery\data\suite\Nested;

use PHPStan\Tally\Attribute\AfterMethods;
use PHPStan\Tally\Attribute\BeforeMethods;
use PHPStan\Tally\Attribute\DataProvider;
use PHPStan\Tally\Attribute\Expected;

#[BeforeMethods('setUp')]
final class LoopBench
{

	/**
	 * @return iterable<string, array{int}>
	 */
	public static function sizes(): iterable
	{
		yield 'size-1000' => [1000];
		yield 'size-10' => [10];
		yield 'size-100' => [100];
	}

	public function setUp(): void
	{
	}

	public function tearDown(): void
	{
	}

	#[DataProvider('sizes')]
	#[BeforeMethods('prepare')]
	#[AfterMethods('tearDown')]
	#[Expected(dataSet: 'size-10', instructions: '100')]
	#[Expected(dataSet: 'size-100', instructions: '1000')]
	public function benchSum(int $size): void
	{
	}

	public function prepare(int $size): void
	{
	}

}
