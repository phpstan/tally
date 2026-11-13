<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Constructors;

final class WithUntyped
{

	/** @phpstan-ignore missingType.parameter */
	public function __construct($meter)
	{
	}

}
