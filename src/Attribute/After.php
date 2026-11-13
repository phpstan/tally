<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;

/**
 * Like #[Before], called after the benchmark, also when it throws.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class After
{

}
