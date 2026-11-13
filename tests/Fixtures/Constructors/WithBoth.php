<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Constructors;

use Tally\Meter;
use Tally\ScratchDirectory;

final class WithBoth
{

	public function __construct(public readonly ScratchDirectory $scratch, public readonly Meter $meter)
	{
	}

}
