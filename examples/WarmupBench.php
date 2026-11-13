<?php declare(strict_types = 1);

namespace Tally\Examples;

use Tally\Attribute\Expected;
use Tally\Attribute\Warmup;
use function preg_match;

/**
 * #[Warmup] calls the benchmark before counting it, so the counted call finds caches filled and
 * classes autoloaded, like every call but the first in a long-running process. Here, PHP keeps
 * compiled regular expressions: the cold call counts compiling the pattern, the warm one does not.
 */
final class WarmupBench
{

	#[Expected(instructions: '74.63K', cycles: '257.8K', memory: '48B')]
	public function benchCold(): int|false
	{
		return preg_match('~^(?<user>[a-z]+)@(?<domain>[a-z]+)\.cold$~', 'tally@example.cold');
	}

	#[Warmup(1)]
	#[Expected(instructions: '864', cycles: '13.18K', memory: '0B')]
	public function benchWarm(): int|false
	{
		return preg_match('~^(?<user>[a-z]+)@(?<domain>[a-z]+)\.warm$~', 'tally@example.warm');
	}

}
