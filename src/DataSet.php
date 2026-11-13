<?php declare(strict_types = 1);

namespace Tally;

use Tally\Expectation\ExpectationSource;
use function array_values;

/**
 * One set of arguments from a data provider. A provider that yields DataSet objects can attach
 * the place the data set's expectations are stored, instead of an #[Expected] attribute.
 *
 * @api
 */
final class DataSet
{

	/**
	 * @param list<mixed> $arguments
	 */
	private function __construct(
		public readonly string $name,
		public readonly array $arguments,
		public readonly ?ExpectationSource $expectations
	)
	{
	}

	/**
	 * @param array<mixed> $arguments
	 */
	public static function named(string $name, array $arguments): self
	{
		return new self($name, array_values($arguments), null);
	}

	public function withExpectations(ExpectationSource $expectations): self
	{
		return new self($this->name, $this->arguments, $expectations);
	}

}
