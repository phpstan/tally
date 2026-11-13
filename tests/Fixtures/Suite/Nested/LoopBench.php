<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Suite\Nested;

use Tally\Attribute\After;
use Tally\Attribute\Before;
use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;

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

	#[Before]
	public function setUp(): void
	{
	}

	#[After]
	public function tearDown(): void
	{
	}

	#[DataProvider('sizes')]
	#[ExpectedFor('size-10', instructions: '100', cycles: '110', memory: '0B')]
	#[ExpectedFor('size-100', instructions: '1000', cycles: '1100', memory: '0B')]
	public function benchSum(int $size): void
	{
	}

	#[Before]
	private function prepare(int $size): void
	{
	}

}
