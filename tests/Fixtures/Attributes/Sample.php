<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Attributes;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_METHOD)]
final class Sample
{

	public function __construct(public readonly int $count, public readonly string $label)
	{
		if ($count < 0) {
			throw new InvalidArgumentException('#[Sample] needs zero or more.');
		}
	}

}
