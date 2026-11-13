<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Comparison;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Comparison\Percent;

final class PercentTest extends TestCase
{

	/**
	 * @return iterable<string, array{float, string, string}>
	 */
	public static function percentProvider(): iterable
	{
		yield 'zero' => [0.0, '0 %', '0.0 %'];
		yield 'a small decrease rounded to zero' => [-0.0004, '0 %', '0.0 %'];
		yield 'a small increase rounded to zero' => [0.0004, '0 %', '0.0 %'];
		yield 'the smallest shown decrease' => [-0.0005, '-0.1 %', '-0.1 %'];
		yield 'a whole percent' => [0.05, '5 %', '+5.0 %'];
		yield 'a fraction below 10' => [0.025, '2.5 %', '+2.5 %'];
		yield 'a fraction from 10' => [0.1049, '10.5 %', '+10.5 %'];
		yield 'rounded to a whole one' => [0.0996, '10 %', '+10.0 %'];
		yield 'a fraction rounded to a whole one below 10' => [0.0499999, '5 %', '+5.0 %'];
		yield 'a third' => [1 / 3, '33.3 %', '+33.3 %'];
		yield 'more than 100' => [5.0, '500 %', '+500.0 %'];
		yield 'just below ten times the original' => [8.9994, '899.9 %', '+899.9 %'];
		yield 'ten times the original' => [9.0, '900 %', '10×'];
		yield 'a ratio, rounded' => [1247.4, '124740 %', '1248×'];
		yield 'negative' => [-0.2, '-20 %', '-20.0 %'];
		yield 'a negative fraction' => [-0.0127, '-1.3 %', '-1.3 %'];
	}

	#[DataProvider('percentProvider')]
	public function testFormat(float $fraction, string $allowance, string $change): void
	{
		$this->assertSame($allowance, (new Percent($fraction))->toString());
		$this->assertSame($change, (new Percent($fraction))->signed());
	}

}
