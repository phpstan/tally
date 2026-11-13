<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Marker
{

}
