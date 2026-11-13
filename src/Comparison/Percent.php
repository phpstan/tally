<?php declare(strict_types = 1);

namespace Tally\Comparison;

use function sprintf;
use function str_ends_with;
use function substr;

/** A fraction shown as a percentage, such as 0.25 as 25 % */
final class Percent
{

	public function __construct(private float $fraction)
	{
	}

	/** One decimal, left out when it is zero: 25 %, 2.5 %, 33.3 % */
	public function toString(): string
	{
		$text = sprintf('%.1f', $this->fraction * 100);

		return (str_ends_with($text, '.0') ? substr($text, 0, -2) : $text) . ' %';
	}

	/** With a sign and one decimal, for a change: +50.0 %, -1.3 % */
	public function signed(): string
	{
		return sprintf('%+.1f %%', $this->fraction * 100);
	}

}
