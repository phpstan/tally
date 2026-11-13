<?php declare(strict_types = 1);

namespace Tally\Quantity;

use function abs;
use function array_reverse;
use function array_search;
use function intdiv;
use function max;
use function preg_match;
use function str_replace;
use function strlen;
use function substr_replace;

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

	/** @var array<int, string> the larger units by their size, from the largest */
	private array $largerUnits;

	/**
	 * @param list<string> $largerUnits the units of $base, $base², and so on
	 */
	private function __construct(private int $base, private string $smallestUnit, array $largerUnits)
	{
		$bySize = [];
		$size = 1;
		foreach ($largerUnits as $unit) {
			$size *= $base;
			$bySize[$size] = $unit;
		}
		$this->largerUnits = array_reverse($bySize, true);
	}

	/** Counts in powers of 1000: 312, 1.204K, 260.9M, 3.119B, 1.500T */
	public static function count(): self
	{
		return new self(1000, '', ['K', 'M', 'B', 'T']);
	}

	/** Bytes in powers of 1024, with binary units so the unit says which base it is: 1023B, 44.49MiB */
	public static function bytes(): self
	{
		return new self(1024, 'B', ['KiB', 'MiB', 'GiB', 'TiB']);
	}

	public function format(int $value): string
	{
		$sign = $value < 0 ? '-' : '';
		$absolute = abs($value);
		$largerSize = null;
		foreach ($this->largerUnits as $size => $unit) {
			if ($absolute < $size) {
				$largerSize = $size;
				continue;
			}

			[$scaled, $decimals] = $this->round($absolute, $size);
			if ($largerSize !== null && $decimals === 0 && $scaled >= $this->base) {
				// 999.95K rounds to 1000K, which is 1.000M
				return $sign . $this->format($largerSize);
			}

			$digits = (string) $scaled;

			return $sign . ($decimals === 0 ? $digits : substr_replace($digits, '.', -$decimals, 0)) . $unit;
		}

		return $sign . $absolute . $this->smallestUnit;
	}

	/**
	 * @throws InvalidQuantityException
	 */
	public function parse(string $text): int
	{
		if (preg_match('~^(-)?(\d+)(?:\.(\d+))?\s*([A-Za-z]*)$~', str_replace('_', '', $text), $matches) !== 1) {
			throw new InvalidQuantityException($text, $this->units());
		}

		[, $minus, $integer, $fraction, $unit] = $matches;
		// a number without a unit is in the smallest unit, plain bytes for bytes
		$multiplier = $unit === '' || $unit === $this->smallestUnit ? 1 : array_search($unit, $this->largerUnits, true);
		if ($multiplier === false || $fraction !== '' && $multiplier === 1) {
			throw new InvalidQuantityException($text, $this->units());
		}

		$divisor = self::powerOfTen(strlen($fraction));
		$value = intdiv(2 * (int) ($integer . $fraction) * $multiplier + $divisor, 2 * $divisor);

		return $minus === '-' ? -$value : $value;
	}

	/**
	 * @return list<string> from the smallest
	 */
	private function units(): array
	{
		return [$this->smallestUnit, ...array_reverse($this->largerUnits)];
	}

	/**
	 * Integer arithmetic, so 999.95 rounds up even though no float holds it exactly.
	 *
	 * @return array{int, int} the value in units of size $unit with the decimals as more digits, and the number of decimals
	 */
	private function round(int $absolute, int $unit): array
	{
		$integerDigits = strlen((string) intdiv($absolute, $unit));
		$decimals = max(0, self::SIGNIFICANT_DIGITS - $integerDigits);
		$scaled = intdiv(2 * $absolute * self::powerOfTen($decimals) + $unit, 2 * $unit);
		if ($decimals > 0 && strlen((string) intdiv($scaled, self::powerOfTen($decimals))) > $integerDigits) {
			// 99.995 rounds to 100.00, one digit too many
			$decimals--;
			$scaled = intdiv(2 * $absolute * self::powerOfTen($decimals) + $unit, 2 * $unit);
		}

		return [$scaled, $decimals];
	}

	private static function powerOfTen(int $exponent): int
	{
		$power = 1;
		for ($i = 0; $i < $exponent; $i++) {
			$power *= 10;
		}

		return $power;
	}

}
