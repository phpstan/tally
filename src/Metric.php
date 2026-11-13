<?php declare(strict_types = 1);

namespace PHPStan\Tally;

use PHPStan\Tally\Quantity\QuantityFormat;

enum Metric: string
{

	/** Instructions executed, counted by CodSpeed's CPU simulation */
	case Instructions = 'instructions';

	/** Instructions plus 10 for each first-level and 100 for each last-level cache miss of the simulated cache */
	case Cycles = 'cycles';

	/** Peak memory above the memory used when counting started, in bytes */
	case Memory = 'memory';

	public function format(): QuantityFormat
	{
		return match ($this) {
			self::Instructions, self::Cycles => QuantityFormat::count(),
			self::Memory => QuantityFormat::bytes(),
		};
	}

}
