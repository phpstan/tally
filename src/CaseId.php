<?php declare(strict_types = 1);

namespace Tally;

use InvalidArgumentException;
use function md5;
use function preg_replace;
use function sprintf;
use function strnatcmp;
use function strpos;
use function substr;

/**
 * Names one benchmark case like PHPUnit's --filter does: Class::method, and @dataSet when the
 * method has a data provider. The ID is also the name of the case's part in Callgrind's profile.
 */
final class CaseId
{

	/** Leaves room for the hash and a suffix within the 255 bytes most file systems allow */
	private const READABLE_FILE_NAME_LIMIT = 100;

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

	/**
	 * A name for files and directories of the case: the ID with other characters than letters,
	 * digits, dots, and dashes replaced, shortened, and made unique by a hash of the whole ID.
	 */
	public function fileName(): string
	{
		$id = $this->toString();
		$readable = substr((string) preg_replace('~[^A-Za-z0-9.-]+~', '-', $id), 0, self::READABLE_FILE_NAME_LIMIT);

		return $readable . '-' . substr(md5($id), 0, 8);
	}

	public function toString(): string
	{
		$id = $this->class . '::' . $this->method;

		return $this->dataSet === null ? $id : $id . '@' . $this->dataSet;
	}

}
