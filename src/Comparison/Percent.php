<?php declare(strict_types = 1);

namespace Tally\Comparison;

use function sprintf;
use function str_ends_with;
use function str_starts_with;
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
		$text = $this->rounded();

		return (str_ends_with($text, '.0') ? substr($text, 0, -2) : $text) . ' %';
	}

	/** With a sign and one decimal, for a change: +50.0 %, -1.3 %, and 0.0 % for what rounds to no change */
	public function signed(): string
	{
		$text = $this->rounded();

		return ($text === '0.0' || str_starts_with($text, '-') ? $text : '+' . $text) . ' %';
	}

	/** One decimal, without the sign of a negative number that rounds to zero */
	private function rounded(): string
	{
		$text = sprintf('%.1f', $this->fraction * 100);

		return $text === '-0.0' ? '0.0' : $text;
	}

}
