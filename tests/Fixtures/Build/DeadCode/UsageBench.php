<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Build;

use Tally\Attribute\After;
use Tally\Attribute\Before;
use Tally\Attribute\DataProvider;

final class UsageBench
{

	#[Before]
	public function setUp(): void
	{
	}

	#[Before]
	private function prepare(): void
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
	public function benchSized(int $size): void
	{
	}

	#[After]
	private function tearDown(): void
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
