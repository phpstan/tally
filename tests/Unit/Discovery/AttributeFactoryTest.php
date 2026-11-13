<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Discovery;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tally\Discovery\AttributeFactory;
use Tally\Discovery\InvalidBenchmarkException;
use Tally\Discovery\ValueTypeCheck;
use Tally\Tests\Fixtures\Attributes\AttributesBench;
use function get_object_vars;

final class AttributeFactoryTest extends TestCase
{

	/**
	 * @return iterable<string, array{string, array<string, mixed>}> the method and the properties of its first attribute
	 */
	public static function dataCreate(): iterable
	{
		yield 'positional arguments' => ['positional', ['count' => 3, 'label' => 'positional']];
		yield 'named arguments in another order' => ['named', ['count' => 3, 'label' => 'named']];
		yield 'positional and named arguments' => ['mixed', ['count' => 3, 'label' => 'mixed']];
		yield 'null for a nullable parameter, without the optional ones' => ['nullAndOptional', ['tags' => [], 'share' => null, 'key' => 0, 'note' => null]];
		yield 'an int for a float, a union, mixed, and variadic arguments' => ['everyKind', ['tags' => ['a', 'b'], 'share' => 1.0, 'key' => 'key', 'note' => [1]]];
		yield 'an unknown name goes to the variadic parameter' => ['namedIntoVariadic', ['tags' => ['extra' => 'x'], 'share' => 0.5, 'key' => 0, 'note' => null]];
		yield 'a repeatable attribute, repeated' => ['repeatable', ['name' => 'a']];
		yield 'an attribute without a constructor' => ['marker', []];
	}

	/**
	 * @param array<string, mixed> $properties
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('dataCreate')]
	public function testCreate(string $method, array $properties): void
	{
		$this->assertSame($properties, get_object_vars(self::create($method)));
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function dataInvalid(): iterable
	{
		yield 'a missing argument' => ['missingOne', '#[Sample] needs argument label.'];
		yield 'missing arguments' => ['missingTwo', '#[Sample] needs arguments count and label.'];
		yield 'an argument too many' => ['tooMany', '#[Sample] takes 2 arguments, 3 given.'];
		yield 'an argument too many for one' => ['tooManyOne', '#[Tag] takes 1 argument, 2 given.'];
		yield 'an argument for none' => ['tooManyNone', '#[Marker] takes no arguments, 1 given.'];
		yield 'an unknown argument' => ['unknown', '#[Sample] has no argument lable, only count and label.'];
		yield 'a named argument for none' => ['unknownNone', '#[Marker] has no argument calls, it takes no arguments.'];
		yield 'an argument passed twice' => ['twice', '#[Sample] gets argument count twice.'];
		yield 'an argument of the wrong type' => ['wrongType', '#[Sample] argument count must be int, string given.'];
		yield 'null for a parameter that is not nullable' => ['nullNotAllowed', '#[Sample] argument count must be int, null given.'];
		yield 'the wrong type for a nullable parameter' => ['wrongNullable', '#[Flexible] argument share must be ?float, string given.'];
		yield 'the wrong type for a variadic parameter' => ['wrongVariadic', '#[Flexible] argument tags must be string, int given.'];
		yield 'a value the attribute refuses' => ['invalidValue', '#[Sample] needs zero or more.'];
		yield 'repeated' => ['repeated', '#[Sample] can be used only once.'];
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	#[DataProvider('dataInvalid')]
	public function testInvalid(string $method, string $message): void
	{
		$this->expectException(InvalidBenchmarkException::class);
		$this->expectExceptionMessage(AttributesBench::class . '::' . $method . '(): ' . $message);
		self::create($method);
	}

	/**
	 * @throws InvalidBenchmarkException
	 */
	private static function create(string $method): object
	{
		$reflection = new ReflectionMethod(AttributesBench::class, $method);

		return (new AttributeFactory(new ValueTypeCheck()))->create($reflection->getAttributes()[0] ?? throw new LogicException('The method has no attribute.'), AttributesBench::class . '::' . $method . '()');
	}

}
