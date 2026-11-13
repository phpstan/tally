<?php declare(strict_types = 1);

namespace PHPStan\Tally\Discovery\data\suite;

use PHPStan\Tally\Attribute\Expected;
use PHPStan\Tally\Attribute\Warmup;
use function sprintf;

final class StringBench
{

	#[Expected(instructions: '1.204K', cycles: '1.387K', memory: '0B')]
	public function benchSprintf(): void
	{
		sprintf('%s-%d', 'a', 1);
	}

	#[Warmup(2)]
	public function benchConcat(): void
	{
	}

	public function helper(): void
	{
	}

	public static function benchStatic(): void
	{
	}

	private function benchPrivate(): void
	{
	}

}
