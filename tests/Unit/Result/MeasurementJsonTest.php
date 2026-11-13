<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Result;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\CaseId;
use Tally\Measurement;
use Tally\Metrics;
use Tally\Result\InvalidResultException;
use Tally\Result\MeasurementJson;

final class MeasurementJsonTest extends TestCase
{

	public function testEncodesAMeasurement(): void
	{
		$measurement = Measurement::measured(
			CaseId::fromString('App\FooBench::benchFoo@a/b'),
			Metrics::fromArray(['memory' => 3, 'instructions' => 1]),
		);

		$this->assertSame(<<<'JSON'
			{
			    "id": "App\\FooBench::benchFoo@a/b",
			    "metrics": {
			        "instructions": 1,
			        "memory": 3
			    }
			}

			JSON, (new MeasurementJson())->encode($measurement));
	}

	public function testEncodesAFailure(): void
	{
		$measurement = Measurement::failed(CaseId::fromString('App\FooBench::benchFoo'), "It broke.\nSee the log.");

		$this->assertSame(<<<'JSON'
			{
			    "id": "App\\FooBench::benchFoo",
			    "failure": "It broke.\nSee the log."
			}

			JSON, (new MeasurementJson())->encode($measurement));
	}

	/**
	 * @throws InvalidResultException
	 */
	public function testReplacesInvalidUtf8InAFailure(): void
	{
		$json = new MeasurementJson();

		$this->assertSame("Bad byte \u{FFFD}.", $json->decode($json->encode(Measurement::failed(CaseId::fromString('A::b'), "Bad byte \xFF.")))->failure);
	}

	/**
	 * @throws InvalidResultException
	 */
	public function testDecodesWhatItEncodes(): void
	{
		$json = new MeasurementJson();
		$measured = $json->decode($json->encode(Measurement::measured(
			CaseId::fromString('App\FooBench::benchFoo@a'),
			Metrics::fromArray(['instructions' => 1, 'cycles' => 2, 'memory' => 3]),
		)));
		$failed = $json->decode($json->encode(Measurement::failed(CaseId::fromString('App\FooBench::benchBar'), 'It broke.')));

		$this->assertSame('App\FooBench::benchFoo@a', $measured->id->toString());
		$this->assertSame(['instructions' => 1, 'cycles' => 2, 'memory' => 3], $measured->metrics->toArray());
		$this->assertNull($measured->failure);
		$this->assertSame('App\FooBench::benchBar', $failed->id->toString());
		$this->assertSame([], $failed->metrics->toArray());
		$this->assertSame('It broke.', $failed->failure);
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function invalidProvider(): iterable
	{
		yield 'not JSON' => ['{', 'it is not JSON.'];
		yield 'not an object' => ['[1]', 'it has no case ID.'];
		yield 'no ID' => ['{"metrics": {}}', 'it has no case ID.'];
		yield 'an ID that is not one' => ['{"id": "FooBench", "metrics": {}}', 'it has no case ID.'];
		yield 'an ID that is not a string' => ['{"id": 1, "metrics": {}}', 'it has no case ID.'];
		yield 'neither metrics nor failure' => ['{"id": "A::b"}', 'it has neither metrics nor a failure.'];
		yield 'metrics that are not an object' => ['{"id": "A::b", "metrics": 1}', 'it has neither metrics nor a failure.'];
		yield 'a failure that is not a string' => ['{"id": "A::b", "failure": 1}', 'it has neither metrics nor a failure.'];
		yield 'an unknown metric' => ['{"id": "A::b", "metrics": {"peakMemory": 1}}', 'Unknown metric peakMemory, or its value is not an integer.'];
	}

	/**
	 * @throws InvalidResultException
	 */
	#[DataProvider('invalidProvider')]
	public function testRejectsInvalidJson(string $json, string $message): void
	{
		$this->expectException(InvalidResultException::class);
		$this->expectExceptionMessage($message);

		(new MeasurementJson())->decode($json);
	}

}
