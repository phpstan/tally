<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Environment;

use RuntimeException;
use function getenv;
use function sprintf;
use function var_export;

final class EnvironmentBench
{

	public function benchEnvironment(): void
	{
		throw new RuntimeException(sprintf('TALLY_EXAMPLE is %s, PATH is %s.', var_export(getenv('TALLY_EXAMPLE'), true), getenv('PATH') === false ? 'unset' : 'set'));
	}

}
