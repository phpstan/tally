<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Compare;

use Tally\Attribute\Expected;

final class CompareBench
{

	#[Expected(instructions: '1K', cycles: '1.5K', memory: '0B')]
	public function benchOk(): void
	{
	}

	#[Expected(instructions: '1K', cycles: '1.5K', memory: '0B')]
	public function benchSlower(): void
	{
	}

	public function benchMissing(): void
	{
	}

}
