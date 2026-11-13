<?php declare(strict_types = 1);

namespace Tally\Examples;

use JsonException;
use Tally\Attribute\BeforeMethods;
use Tally\ScratchDirectory;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function range;
use const JSON_THROW_ON_ERROR;

/**
 * Before methods prepare what the benchmark needs, outside of counting. A ScratchDirectory
 * parameter gets a directory of the case's own, removed after the run.
 */
#[BeforeMethods('writeFile')]
final class FileBench
{

	private const FILE = '/numbers.json';

	/**
	 * @throws JsonException
	 */
	public function writeFile(ScratchDirectory $scratch): void
	{
		file_put_contents($scratch->path() . self::FILE, json_encode(range(1, 1_000), JSON_THROW_ON_ERROR));
	}

	/**
	 * @throws JsonException
	 */
	public function benchDecode(ScratchDirectory $scratch): void
	{
		json_decode((string) file_get_contents($scratch->path() . self::FILE), true, 2, JSON_THROW_ON_ERROR);
	}

}
