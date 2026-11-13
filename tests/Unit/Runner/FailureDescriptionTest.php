<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Runner;

use Closure;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Tally\Quantity\QuantityFormat;
use Tally\Report\RelativePath;
use Tally\Runner\FailureDescription;
use Tally\Runner\MeterStateException;
use Throwable;
use function dirname;

final class FailureDescriptionTest extends TestCase
{

	/**
	 * @return iterable<string, array{Closure(): Throwable, string, string}> the failure, Tally's directory, and the description
	 */
	public static function dataDescribe(): iterable
	{
		$root = dirname(__DIR__, 3);

		yield 'an exception of the benchmark, with where it was thrown' => [static fn (): Throwable => new RuntimeException('It broke.'), $root, "RuntimeException: It broke.\nat tests/Unit/Runner/FailureDescriptionTest.php:" . __LINE__];
		yield "Tally's own exception, without its class" => [static fn (): Throwable => new MeterStateException('The meter is paused.'), $root, "The meter is paused.\nat tests/Unit/Runner/FailureDescriptionTest.php:" . __LINE__];
		yield "at the first frame outside of Tally's code, which threw it" => [static fn (): Throwable => self::caught(static fn () => QuantityFormat::count()->parse('many')), $root, "\"many\" is not a quantity, such as 3.119B or 44.49MiB. Units: K, M, B, T.\nat tests/Unit/Runner/FailureDescriptionTest.php:" . __LINE__];
		yield "in Tally's code only, without where" => [
			static fn (): Throwable => self::thrownAt(new RuntimeException('It broke.'), $root . '/src/Runner/CaseRunner.php', [['file' => $root . '/bin/tally', 'line' => 44], ['function' => 'main']]),
			$root,
			'RuntimeException: It broke.',
		];
		yield 'a frame outside of Tally after its own' => [
			static fn (): Throwable => self::thrownAt(new RuntimeException('It broke.'), $root . '/src/Runner/CaseRunner.php', [['file' => $root . '/benchmarks/SortBench.php', 'line' => 12]]),
			$root,
			"RuntimeException: It broke.\nat benchmarks/SortBench.php:12",
		];
		yield 'a file outside the working directory, as it is' => [
			static fn (): Throwable => self::thrownAt(new RuntimeException('It broke.'), '/elsewhere/SortBench.php', []),
			$root,
			"RuntimeException: It broke.\nat /elsewhere/SortBench.php:1",
		];
		yield "a directory that only starts like Tally's src is not Tally" => [
			static fn (): Throwable => self::thrownAt(new RuntimeException('It broke.'), $root . '/srcs/SortBench.php', []),
			$root,
			"RuntimeException: It broke.\nat srcs/SortBench.php:1",
		];
	}

	/**
	 * @param Closure(): Throwable $failure
	 */
	#[DataProvider('dataDescribe')]
	public function testDescribe(Closure $failure, string $tallyDirectory, string $description): void
	{
		$this->assertSame($description, (new FailureDescription(new RelativePath()))->describe($failure(), $tallyDirectory, dirname(__DIR__, 3)));
	}

	/**
	 * @param Closure(): mixed $call
	 */
	private static function caught(Closure $call): Throwable
	{
		try {
			$call();
		} catch (Throwable $e) {
			return $e;
		}

		throw new LogicException('The call did not throw.');
	}

	/**
	 * @param list<array<string, mixed>> $trace
	 */
	private static function thrownAt(RuntimeException $e, string $file, array $trace): Throwable
	{
		// the properties of the base exception, which RuntimeException extends
		$exception = (new ReflectionClass(RuntimeException::class))->getParentClass();
		self::assertNotFalse($exception);
		$exception->getProperty('file')->setValue($e, $file);
		$exception->getProperty('line')->setValue($e, 1);
		$exception->getProperty('trace')->setValue($e, $trace);

		return $e;
	}

}
