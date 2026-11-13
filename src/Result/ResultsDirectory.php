<?php declare(strict_types = 1);

namespace Tally\Result;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Tally\Measurement;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sprintf;
use function str_ends_with;
use function strcmp;
use function usort;

/**
 * The results tally run --output writes, one JSON file per case. Reading searches subdirectories
 * too, like those of the artifacts of a sharded run.
 */
final class ResultsDirectory
{

	public function __construct(private MeasurementJson $json)
	{
	}

	/**
	 * @throws RuntimeException
	 */
	public function write(string $directory, Measurement $measurement): void
	{
		if (!is_dir($directory) && !@mkdir($directory, 0777, true)) {
			throw new RuntimeException(sprintf('Cannot create %s.', $directory));
		}

		$file = $directory . '/' . $measurement->id->fileName() . '.json';
		if (@file_put_contents($file, $this->json->encode($measurement)) === false) {
			throw new RuntimeException(sprintf('Cannot write %s.', $file));
		}
	}

	/**
	 * @return array<string, Measurement> by case ID
	 * @throws InvalidResultException
	 */
	public function read(string $directory): array
	{
		if (!is_dir($directory)) {
			throw new InvalidResultException(sprintf('Results directory %s does not exist.', $directory));
		}

		$files = [];
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
			if (!$file instanceof SplFileInfo || !str_ends_with($file->getFilename(), '.json')) {
				continue;
			}

			$files[] = $file->getPathname();
		}
		// the same case in two files, such as of two runs: the last one in path order wins
		usort($files, strcmp(...));

		$measurements = [];
		foreach ($files as $file) {
			try {
				$measurement = $this->json->decode((string) file_get_contents($file));
			} catch (InvalidResultException $e) {
				throw new InvalidResultException(sprintf('%s is not a Tally result: %s', $file, $e->getMessage()), previous: $e);
			}
			$measurements[$measurement->id->toString()] = $measurement;
		}

		return $measurements;
	}

}
