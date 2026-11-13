<?php declare(strict_types = 1);

namespace Tally\Cli;

use Tally\Measurement;
use Tally\Result\InvalidResultException;
use Tally\Result\ResultsDirectory;
use function array_slice;

/**
 * The arguments of tally compare and tally accept: the results directory, then the benchmarks.
 */
final class ResultsArguments
{

	public function __construct(private ResultsDirectory $results)
	{
	}

	public function usage(): string
	{
		return '<results> [<path>...]';
	}

	/**
	 * @throws UsageException
	 */
	public function directory(Input $input): string
	{
		$directory = $input->arguments()[0] ?? null;
		if ($directory === null) {
			throw new UsageException('Pass the results directory that tally run --output wrote.');
		}

		return $directory;
	}

	/**
	 * @return list<string>
	 */
	public function paths(Input $input): array
	{
		return array_slice($input->arguments(), 1);
	}

	/**
	 * @return array<string, Measurement> by case ID
	 * @throws UsageException
	 */
	public function read(Input $input): array
	{
		try {
			return $this->results->read($this->directory($input));
		} catch (InvalidResultException $e) {
			throw new UsageException($e->getMessage(), previous: $e);
		}
	}

}
