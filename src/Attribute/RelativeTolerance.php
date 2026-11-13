<?php declare(strict_types = 1);

namespace Tally\Attribute;

use Attribute;
use InvalidArgumentException;
use Override;
use Tally\Comparison\Percent;
use Tally\Comparison\TolerancePolicy;
use Tally\Metric;
use function sprintf;

/**
 * Allows each metric to differ from its expected value by the same share, such as
 * #[RelativeTolerance(0.05)] for ±5 %. Benchmarks without a policy get ±10 %.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class RelativeTolerance implements TolerancePolicy
{

	/**
	 * @throws InvalidArgumentException
	 */
	public function __construct(public readonly float $allowed)
	{
		if ($allowed < 0) {
			throw new InvalidArgumentException(sprintf('#[RelativeTolerance] needs zero or more, %s given.', $allowed));
		}
	}

	#[Override]
	public function allowedChange(Metric $metric, int $expected): float
	{
		return $this->allowed;
	}

	#[Override]
	public function describe(): string
	{
		return sprintf('±%s of each metric', (new Percent($this->allowed))->toString());
	}

}
