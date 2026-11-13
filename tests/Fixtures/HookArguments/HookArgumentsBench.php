<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\HookArguments;

use Tally\Attribute\Before;
use Tally\Attribute\DataProvider;

final class HookArgumentsBench
{

	public static function sizes(): iterable
	{
		yield 'size-10' => [10];
	}

	#[Before]
	public function prepare(int $size, string $name): void
	{
	}

	#[DataProvider('sizes')]
	public function benchSize(int $size): void
	{
	}

}
