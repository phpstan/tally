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
		yield 'zero' => [0.0, '0 %', '+0.0 %'];
		yield 'a whole percent' => [0.05, '5 %', '+5.0 %'];
		yield 'a fraction below 10' => [0.025, '2.5 %', '+2.5 %'];
		yield 'a fraction from 10' => [0.1049, '10.5 %', '+10.5 %'];
		yield 'rounded to a whole one' => [0.0996, '10 %', '+10.0 %'];
		yield 'a fraction rounded to a whole one below 10' => [0.0499999, '5 %', '+5.0 %'];
		yield 'a third' => [1 / 3, '33.3 %', '+33.3 %'];
		yield 'more than 100' => [5.0, '500 %', '+500.0 %'];
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
