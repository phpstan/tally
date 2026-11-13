<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Loading\TwoInvalid;

final class ZBench
{

	public function __construct(private int $size)
	{
	}

	public function benchSize(): int
	{
		return $this->size;
	}

}
