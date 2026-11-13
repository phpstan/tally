<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use function array_values;

/**
 * Methods of the benchmark class to call before the benchmark method, outside of counting.
 * They get the same arguments as the benchmark method. On a class, applies to its methods,
 * and runs before the methods named on the method.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class BeforeMethods
{

	/** @var list<string> */
	public readonly array $methods;

	public function __construct(string ...$methods)
	{
		$this->methods = array_values($methods);
	}

}
