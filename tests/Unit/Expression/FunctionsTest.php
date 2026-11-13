<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Expression;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Expression\FunctionLibrary;
use Tally\Expression\Functions\Exponential;
use Tally\Expression\Functions\Factorial;
use Tally\Expression\Functions\Log;
use Tally\Expression\Functions\SquareRoot;
use Tally\Expression\MathFunction;
use Tally\Expression\OutOfDomainException;
use const INF;
use const M_E;

final class FunctionsTest extends TestCase
{

	/**
	 * @return iterable<string, array{MathFunction, float, float}>
	 */
	public static function dataApply(): iterable
	{
		yield 'log of 1' => [new Log(), 1.0, 0.0];
		yield 'log of e' => [new Log(), M_E, 1.0];
		yield 'log of a fraction' => [new Log(), 0.5, -0.6931471805599453];
		yield 'sqrt of 0' => [new SquareRoot(), 0.0, 0.0];
		yield 'sqrt' => [new SquareRoot(), 2.25, 1.5];
		yield 'exp of 0' => [new Exponential(), 0.0, 1.0];
		yield 'exp of a negative number' => [new Exponential(), -1.0, 1 / M_E];
		yield 'factorial of 0' => [new Factorial(), 0.0, 1.0];
		yield 'factorial of 1' => [new Factorial(), 1.0, 1.0];
		yield 'factorial of 2' => [new Factorial(), 2.0, 2.0];
		yield 'factorial of 6' => [new Factorial(), 6.0, 720.0];
		yield 'factorial of the largest finite' => [new Factorial(), 170.0, 7.257415615307994E+306];
		yield 'factorial above the largest finite' => [new Factorial(), 171.0, INF];
		yield 'factorial of a huge number' => [new Factorial(), 1e12, INF];
	}

	/**
	 * @throws OutOfDomainException
	 */
	#[DataProvider('dataApply')]
	public function testApply(MathFunction $function, float $x, float $result): void
	{
		$this->assertSame($result, $function->apply($x));
	}

	/**
	 * @return iterable<string, array{MathFunction, float, string}>
	 */
	public static function dataOutOfDomain(): iterable
	{
		yield 'log of 0' => [new Log(), 0.0, 'a number above 0'];
		yield 'log of a negative number' => [new Log(), -1.0, 'a number above 0'];
		yield 'sqrt of a negative number' => [new SquareRoot(), -0.01, 'a number from 0'];
		yield 'factorial of a negative number' => [new Factorial(), -1.0, 'a whole number from 0'];
		yield 'factorial of a fraction' => [new Factorial(), 2.5, 'a whole number from 0'];
	}

	#[DataProvider('dataOutOfDomain')]
	public function testOutOfDomain(MathFunction $function, float $x, string $needs): void
	{
		try {
			$function->apply($x);
			self::fail('No exception.');
		} catch (OutOfDomainException $e) {
			$this->assertSame($needs, $e->needs);
			$this->assertSame($needs, $e->getMessage());
		}
	}

	/**
	 * @return iterable<string, array{MathFunction, string}>
	 */
	public static function dataNames(): iterable
	{
		yield 'log' => [new Log(), 'log'];
		yield 'sqrt' => [new SquareRoot(), 'sqrt'];
		yield 'exp' => [new Exponential(), 'exp'];
		yield 'factorial' => [new Factorial(), 'factorial'];
	}

	#[DataProvider('dataNames')]
	public function testNames(MathFunction $function, string $name): void
	{
		$this->assertSame($name, $function->name());
	}

	public function testLibrary(): void
	{
		$log = new Log();
		$sqrt = new SquareRoot();
		$library = new FunctionLibrary([$sqrt, $log]);
		$this->assertSame(['log', 'sqrt'], $library->names());
		$this->assertSame($log, $library->find('log'));
		$this->assertSame($sqrt, $library->find('sqrt'));
		$this->assertNull($library->find('exp'));
		$this->assertSame([], (new FunctionLibrary([]))->names());
	}

}
