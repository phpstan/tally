<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Loading\Helpers;

final class MoneyBench
{

	public function benchAdd(): void
	{
		(new Money(1))->add(new Money(2));
	}

}

/** A helper whose constructor Tally could not call */
final class Money
{

	public function __construct(private int $cents)
	{
	}

	public function add(self $other): self
	{
		return new self($this->cents + $other->cents);
	}

}

/** Named like a benchmark class, but not like the file */
final class OtherBench
{

	public function __construct(private int $size)
	{
	}

	public function benchSize(): int
	{
		return $this->size;
	}

}
