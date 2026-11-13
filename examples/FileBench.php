<?php declare(strict_types = 1);

namespace Tally\Examples;

use JsonException;
use Tally\Attribute\Before;
use Tally\Attribute\Expected;
use Tally\ScratchDirectory;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function range;
use const JSON_THROW_ON_ERROR;

/**
 * A #[Before] method prepares what the benchmark needs, outside of counting. A ScratchDirectory
 * parameter gets an empty directory of the case's own.
 */
final class FileBench
{

	private const FILE = '/numbers.json';

	/**
	 * @throws JsonException
	 */
	#[Before]
	public function writeFile(ScratchDirectory $scratch): void
	{
		file_put_contents($scratch->path() . self::FILE, json_encode(range(1, 1_000), JSON_THROW_ON_ERROR));
	}

	/**
	 * @throws JsonException
	 */
	#[Expected(instructions: '601.4K', cycles: '715.3K', memory: '23.84KiB')]
	public function benchDecode(ScratchDirectory $scratch): void
	{
		json_decode((string) file_get_contents($scratch->path() . self::FILE), true, 2, JSON_THROW_ON_ERROR);
	}

}
