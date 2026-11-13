<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Constructors;

use Tally\Meter;

final class WithNullableMeter
{

	public function __construct(public readonly ?Meter $meter)
	{
	}

}
