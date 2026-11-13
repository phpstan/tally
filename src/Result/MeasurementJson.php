<?php declare(strict_types = 1);

namespace Tally\Result;

use InvalidArgumentException;
use JsonException;
use Tally\CaseId;
use Tally\Measurement;
use Tally\Metrics;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The format of a results file: what a worker hands to tally run, and what tally run --output
 * keeps for tally compare and tally accept.
 */
final class MeasurementJson
{

	public function encode(Measurement $measurement): string
	{
		$data = ['id' => $measurement->id->toString()];
		if ($measurement->failure === null) {
			$data['metrics'] = $measurement->metrics->toArray();
		} else {
			$data['failure'] = $measurement->failure;
		}

		// a failure is an exception message, which can hold any bytes
		return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . "\n";
	}

	/**
	 * @throws InvalidResultException
	 */
	public function decode(string $json): Measurement
	{
		try {
			$data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new InvalidResultException('it is not JSON: ' . $e->getMessage() . '.', previous: $e);
		}

		if (!is_array($data) || !is_string($data['id'] ?? null)) {
			throw new InvalidResultException('it has no case ID.');
		}
		try {
			$id = CaseId::fromString($data['id']);
		} catch (InvalidArgumentException $e) {
			throw new InvalidResultException('it has no case ID.', previous: $e);
		}

		$metrics = $data['metrics'] ?? null;
		if (is_array($metrics)) {
			try {
				return Measurement::measured($id, Metrics::fromArray($metrics));
			} catch (InvalidArgumentException $e) {
				throw new InvalidResultException($e->getMessage(), previous: $e);
			}
		}

		$failure = $data['failure'] ?? null;
		if (!is_string($failure)) {
			throw new InvalidResultException('it has neither metrics nor a failure.');
		}

		return Measurement::failed($id, $failure);
	}

}
