<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Constructors;

use Tally\Meter;

final class WithMeterTwice
{

	public function __construct(Meter $meter, Meter $again)
	{
	}

}
