<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Tag
{

	public function __construct(public readonly string $name)
	{
	}

}
