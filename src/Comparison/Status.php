<?php declare(strict_types = 1);

namespace Tally\Comparison;

use function in_array;

/**
 * How a measured metric, or a whole case, compares with what is expected. The cases are ordered
 * from the worst: a case gets the worst status of its metrics.
 *
 * @api
 */
enum Status: string
{

	case Slower = 'slower';

	/** The case failed, timed out, or did not run */
	case NotMeasured = 'not measured';

	/** Nothing was expected yet */
	case New = 'new';

	case Faster = 'faster';

	case Ok = 'ok';

	/** Faster and new cases pass, but their numbers should be accepted */
	public function fails(): bool
	{
		return $this === self::Slower || $this === self::NotMeasured;
	}

	/**
	 * @param list<self> $statuses
	 */
	public static function worst(array $statuses): self
	{
		foreach (self::cases() as $status) {
			if (in_array($status, $statuses, true)) {
				return $status;
			}
		}

		return self::Ok;
	}

}
