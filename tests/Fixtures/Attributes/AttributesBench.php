<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Attributes;

final class AttributesBench
{

	#[Sample(3, 'positional')]
	public function positional(): void
	{
	}

	#[Sample(label: 'named', count: 3)]
	public function named(): void
	{
	}

	#[Sample(3, label: 'mixed')]
	public function mixed(): void
	{
	}

	#[Flexible(null)]
	public function nullAndOptional(): void
	{
	}

	#[Flexible(1, 'key', [1], 'a', 'b')]
	public function everyKind(): void
	{
	}

	#[Flexible(share: 0.5, extra: 'x')]
	public function namedIntoVariadic(): void
	{
	}

	#[Tag('a')]
	#[Tag('b')]
	public function repeatable(): void
	{
	}

	#[Marker]
	public function marker(): void
	{
	}

	#[Sample(3)]
	public function missingOne(): void
	{
	}

	#[Sample]
	public function missingTwo(): void
	{
	}

	#[Sample(3, 'label', 4)]
	public function tooMany(): void
	{
	}

	#[Tag('a', 'b')]
	public function tooManyOne(): void
	{
	}

	#[Marker(1)]
	public function tooManyNone(): void
	{
	}

	#[Sample(3, lable: 'label')]
	public function unknown(): void
	{
	}

	#[Marker(calls: 1)]
	public function unknownNone(): void
	{
	}

	#[Sample(3, 'label', count: 4)]
	public function twice(): void
	{
	}

	#[Sample('3', 'label')]
	public function wrongType(): void
	{
	}

	#[Sample(null, 'label')]
	public function nullNotAllowed(): void
	{
	}

	#[Flexible('half')]
	public function wrongNullable(): void
	{
	}

	#[Flexible(0.5, 1, null, 'a', 2)]
	public function wrongVariadic(): void
	{
	}

	#[Sample(-1, 'label')]
	public function invalidValue(): void
	{
	}

	#[Sample(3, 'label')]
	#[Sample(4, 'label')]
	public function repeated(): void
	{
	}

}
