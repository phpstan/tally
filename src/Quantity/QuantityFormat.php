<?php declare(strict_types = 1);

namespace Tally\Quantity;

use function array_search;
use function count;
use function intdiv;
use function preg_match;
use function str_pad;
use function str_replace;
use function strlen;
use const STR_PAD_LEFT;

/**
 * Formats a measured number for people to read and write, with four significant digits
 * and a unit, such as 3.119B instructions or 44.49MiB, and parses it back.
 *
 * Rounding to four significant digits moves a number by at most 0.05 %, so formatting a
 * parsed number again gives the same text and accepting new numbers does not churn.
 */
final class QuantityFormat
{

	private const SIGNIFICANT_DIGITS = 4;

	/**
	 * @param list<string> $units the unit of each power of $base, from the power 0
	 */
	private function __construct(private int $base, private array $units)
	{
	}

	/** Counts in powers of 1000: 312, 1.204K, 260.9M, 3.119B, 1.500T */
	public static function count(): self
	{
		return new self(1000, ['', 'K', 'M', 'B', 'T']);
	}

	/** Bytes in powers of 1024, with binary units so the unit says which base it is: 1023B, 44.49MiB */
	public static function bytes(): self
	{
		return new self(1024, ['B', 'KiB', 'MiB', 'GiB', 'TiB']);
	}

	public function format(int $value): string
	{
		$sign = $value < 0 ? '-' : '';
		$absolute = $value < 0 ? -$value : $value;
		if ($absolute < $this->base) {
			return $sign . $absolute . $this->units[0];
		}

		$power = 0;
		while ($power < count($this->units) - 1 && $absolute >= $this->base ** ($power + 1)) {
			$power++;
		}

		[$scaled, $decimals] = $this->round($absolute, $power);
		if ($scaled >= $this->base * 10 ** $decimals && $power < count($this->units) - 1) {
			// 999.95K rounds to 1000K, which is 1.000M
			$power++;
			[$scaled, $decimals] = $this->round($absolute, $power);
		}

		$factor = 10 ** $decimals;
		$text = (string) intdiv($scaled, $factor);
		if ($decimals > 0) {
			$text .= '.' . str_pad((string) ($scaled % $factor), $decimals, '0', STR_PAD_LEFT);
		}

		return $sign . $text . $this->units[$power];
	}

	/**
	 * @throws InvalidQuantityException
	 */
	public function parse(string $text): int
	{
		if (preg_match('~^(-)?(\d+)(?:\.(\d+))?\s*([A-Za-z]*)$~', str_replace('_', '', $text), $matches) !== 1) {
			throw new InvalidQuantityException($text, $this->units);
		}

		$unit = $matches[4];
		// a number without a unit is in the smallest unit, plain bytes for bytes
		$power = $unit === '' ? 0 : array_search($unit, $this->units, true);
		if ($power === false) {
			throw new InvalidQuantityException($text, $this->units);
		}

		$fraction = $matches[3];
		$multiplier = $this->base ** $power;
		if ($fraction !== '' && $multiplier === 1) {
			throw new InvalidQuantityException($text, $this->units);
		}

		$divisor = 10 ** strlen($fraction);
		$digits = (int) ($matches[2] . $fraction);
		$value = intdiv(2 * $digits * $multiplier + $divisor, 2 * $divisor);

		return $matches[1] === '-' ? -$value : $value;
	}

	/**
	 * Integer arithmetic, so 999.95 rounds up even though no float holds it exactly.
	 *
	 * @return array{int, int} the value in units of $power with the decimals as more digits, and the number of decimals
	 */
	private function round(int $absolute, int $power): array
	{
		$unit = $this->base ** $power;
		$integerDigits = strlen((string) intdiv($absolute, $unit));
		$decimals = self::SIGNIFICANT_DIGITS > $integerDigits ? self::SIGNIFICANT_DIGITS - $integerDigits : 0;
		$scaled = intdiv(2 * $absolute * 10 ** $decimals + $unit, 2 * $unit);
		if ($decimals > 0 && strlen((string) intdiv($scaled, 10 ** $decimals)) > $integerDigits) {
			// 99.995 rounds to 100.00, one digit too many
			$decimals--;
			$scaled = intdiv(2 * $absolute * 10 ** $decimals + $unit, 2 * $unit);
		}

		return [$scaled, $decimals];
	}

}
