<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Loading\Constructor;

final class SizedBench
{

	public function __construct(private int $size)
	{
	}

	public function benchSize(): int
	{
		return $this->size;
	}

}
