<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Discovery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tally\Discovery\ConstructorArguments;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Runner\MeterSession;
use Tally\ScratchDirectory;
use Tally\Tests\FakeMemory;
use Tally\Tests\Fixtures\Constructors\WithBoth;
use Tally\Tests\Fixtures\Constructors\WithData;
use Tally\Tests\Fixtures\Constructors\WithMeterTwice;
use Tally\Tests\Fixtures\Constructors\WithNullableMeter;
use Tally\Tests\Fixtures\Constructors\WithoutConstructor;
use Tally\Tests\Fixtures\Constructors\WithUnion;
use Tally\Tests\Fixtures\Constructors\WithUntyped;

final class ConstructorArgumentsTest extends TestCase
{

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testByTypeInAnyOrder(): void
	{
		$meter = new MeterSession(new FakeMemory(0));
		$scratch = new ScratchDirectory('/tmp/scratch');

		$this->assertSame([$scratch, $meter], (new ConstructorArguments())->resolve(new ReflectionClass(WithBoth::class), $meter, $scratch));
	}

	/**
	 * @return iterable<string, array{class-string}>
	 */
	public static function dataValid(): iterable
	{
		yield 'no constructor' => [WithoutConstructor::class];
		yield 'both' => [WithBoth::class];
		yield 'nullable' => [WithNullableMeter::class];
		yield 'the same service twice' => [WithMeterTwice::class];
	}

	/**
	 * @param class-string $class
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('dataValid')]
	public function testValid(string $class): void
	{
		$meter = new MeterSession(new FakeMemory(0));
		$arguments = new ConstructorArguments();
		$reflection = new ReflectionClass($class);

		$arguments->check($reflection);
		$this->assertInstanceOf($class, $reflection->newInstanceArgs($arguments->resolve($reflection, $meter, new ScratchDirectory('/tmp'))));
	}

	/**
	 * @return iterable<string, array{class-string, string}>
	 */
	public static function dataInvalid(): iterable
	{
		$takes = 'Tally creates the class with a Meter and a ScratchDirectory only, by the types of the parameters. Data comes from a data provider.';
		yield 'a data parameter' => [WithData::class, WithData::class . '::__construct(): parameter $size is neither a Meter nor a ScratchDirectory. ' . $takes];
		yield 'an untyped parameter' => [WithUntyped::class, WithUntyped::class . '::__construct(): parameter $meter is neither a Meter nor a ScratchDirectory. ' . $takes];
		yield 'a union' => [WithUnion::class, WithUnion::class . '::__construct(): parameter $service is neither a Meter nor a ScratchDirectory.'];
	}

	/**
	 * @param class-string $class
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('dataInvalid')]
	public function testInvalid(string $class, string $message): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage($message);

		(new ConstructorArguments())->check(new ReflectionClass($class));
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	public function testResolveChecksToo(): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage('parameter $size is neither');

		(new ConstructorArguments())->resolve(new ReflectionClass(WithData::class), new MeterSession(new FakeMemory(0)), new ScratchDirectory('/tmp'));
	}

}
