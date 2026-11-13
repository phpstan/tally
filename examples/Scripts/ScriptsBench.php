<?php declare(strict_types = 1);

namespace Tally\Examples\Scripts;

use Tally\Attribute\DataProvider;
use Tally\DataSet;
use Tally\Quantity\InvalidQuantityException;
use function basename;
use function file_get_contents;
use function glob;
use function sort;

/**
 * One case per file of a directory, with the expected numbers in each file instead of in
 * attributes. The data provider attaches an ExpectationSource to each data set; tally accept
 * and the patch for new cases write through it.
 */
final class ScriptsBench
{

	/**
	 * @return iterable<string, DataSet>
	 * @throws InvalidQuantityException
	 */
	public static function scripts(): iterable
	{
		$header = new ScriptHeader();
		$files = glob(__DIR__ . '/scripts/*.php');
		$files = $files === false ? [] : $files;
		sort($files);
		foreach ($files as $file) {
			$name = basename($file, '.php');
			yield $name => DataSet::named($name, [$file])
				->withExpectations(new ScriptExpectations($header, $file, $header->read((string) file_get_contents($file))));
		}
	}

	#[DataProvider('scripts')]
	public function benchScript(string $file): void
	{
		require $file;
	}

}
