<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Build;

use Tally\Attribute\AfterMethods;
use Tally\Attribute\BeforeMethods;
use Tally\Attribute\DataProvider;

#[BeforeMethods('setUp', 'prepare')]
final class UsageBench
{

	public function setUp(): void
	{
	}

	public function prepare(): void
	{
	}

	public function benchPlain(): void
	{
	}

	public static function sizes(): iterable
	{
		yield [1];
	}

	#[DataProvider('sizes')]
	#[AfterMethods('tearDown')]
	public function benchSized(int $size): void
	{
	}

	public function tearDown(): void
	{
	}

	public static function benchStatic(): void
	{
	}

	private function benchPrivate(): void
	{
	}

	public function helper(): void
	{
	}

}

final class UsageHelper
{

	public function benchNotInABenchClass(): void
	{
	}

}
