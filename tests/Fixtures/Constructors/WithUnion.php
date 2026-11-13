<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Constructors;

use Tally\Meter;
use Tally\ScratchDirectory;

final class WithUnion
{

	public function __construct(Meter|ScratchDirectory $service)
	{
	}

}
