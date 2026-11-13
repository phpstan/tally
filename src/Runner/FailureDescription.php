<?php declare(strict_types = 1);

namespace Tally\Runner;

use ReflectionClass;
use Tally\Report\RelativePath;
use Throwable;
use function sprintf;
use function str_starts_with;

/**
 * What a report says about a case that threw: the message, with the class of the exception unless
 * it is Tally's own, and where in the benchmark's code it happened. Frames in Tally's own code are
 * skipped, so a failure Tally raises for the benchmark points to the benchmark.
 */
final class FailureDescription
{

	/** The directories of Tally's own code, relative to its installation */
	private const TALLY_CODE = ['/src/', '/bin/'];

	public function __construct(private RelativePath $relativePath)
	{
	}

	/**
	 * @param string $tallyDirectory where Tally is installed
	 * @param string $workingDirectory paths are relative to it
	 */
	public function describe(Throwable $failure, string $tallyDirectory, string $workingDirectory): string
	{
		$description = $this->isTally((string) (new ReflectionClass($failure))->getFileName(), $tallyDirectory)
			? $failure->getMessage()
			: sprintf('%s: %s', $failure::class, $failure->getMessage());

		foreach ([['file' => $failure->getFile(), 'line' => $failure->getLine()], ...$failure->getTrace()] as $frame) {
			$file = $frame['file'] ?? null;
			if ($file === null || $this->isTally($file, $tallyDirectory)) {
				continue;
			}

			return sprintf("%s\nat %s:%d", $description, $this->relativePath->of($file, $workingDirectory), $frame['line'] ?? 0);
		}

		return $description;
	}

	private function isTally(string $file, string $tallyDirectory): bool
	{
		foreach (self::TALLY_CODE as $directory) {
			if (str_starts_with($file, $tallyDirectory . $directory)) {
				return true;
			}
		}

		return false;
	}

}
