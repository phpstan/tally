<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Attributes;

use Attribute;

/** Like a tolerance policy a user writes, with the parameters Tally's own attributes do not have */
#[Attribute(Attribute::TARGET_METHOD)]
final class Flexible
{

	/** @var array<string> */
	public readonly array $tags;

	public function __construct(public readonly ?float $share, public readonly int|string $key = 0, public readonly mixed $note = null, string ...$tags)
	{
		$this->tags = $tags;
	}

}
