<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Run;

use RuntimeException;
use Tally\Attribute\Expected;

final class RunBench
{

	#[Expected(instructions: '1K', cycles: '1.5K', memory: '0B')]
	public function benchOk(): void
	{
	}

	#[Expected(instructions: '1K', cycles: '1.5K', memory: '0B')]
	public function benchSlower(): void
	{
	}

	public function benchNew(): void
	{
	}

	public function benchThrows(): void
	{
		throw new RuntimeException('It broke.');
	}

}
