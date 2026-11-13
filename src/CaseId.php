<?php declare(strict_types = 1);

namespace Tally;

use InvalidArgumentException;
use function sprintf;
use function strnatcmp;
use function strpos;
use function substr;

/**
 * Names one benchmark case like PHPUnit's --filter does: Class::method, and @dataSet when the
 * method has a data provider. The ID is also the name of the case's part in CodSpeed's profile.
 */
final class CaseId
{

	/**
	 * @param class-string|string $class
	 */
	public function __construct(
		public readonly string $class,
		public readonly string $method,
		public readonly ?string $dataSet
	)
	{
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public static function fromString(string $id): self
	{
		$separator = strpos($id, '::');
		if ($separator === false) {
			throw new InvalidArgumentException(sprintf('"%s" is not a case ID, such as App\FooBench::benchBar@dataSet.', $id));
		}

		$class = substr($id, 0, $separator);
		$method = substr($id, $separator + 2);
		$dataSetSeparator = strpos($method, '@');
		if ($dataSetSeparator === false) {
			return new self($class, $method, null);
		}

		return new self($class, substr($method, 0, $dataSetSeparator), substr($method, $dataSetSeparator + 1));
	}

	/** Natural order, so bug-1388 comes before bug-10147 */
	public static function compare(self $a, self $b): int
	{
		return strnatcmp($a->toString(), $b->toString());
	}

	public function toString(): string
	{
		$id = $this->class . '::' . $this->method;

		return $this->dataSet === null ? $id : $id . '@' . $this->dataSet;
	}

}
