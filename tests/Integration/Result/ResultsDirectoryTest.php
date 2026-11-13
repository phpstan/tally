<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Result;

use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tally\CaseId;
use Tally\Execution\DirectoryRemover;
use Tally\Measurement;
use Tally\Metric;
use Tally\Metrics;
use Tally\Result\InvalidResultException;
use Tally\Result\MeasurementJson;
use Tally\Result\ResultsDirectory;
use function array_map;
use function chmod;
use function getmypid;
use function ksort;
use function md5;
use function mkdir;
use function substr;
use function sys_get_temp_dir;

final class ResultsDirectoryTest extends TestCase
{

	private string $directory;

	#[Override]
	protected function setUp(): void
	{
		$this->directory = sys_get_temp_dir() . '/tally-results-' . getmypid();
	}

	#[Override]
	protected function tearDown(): void
	{
		(new DirectoryRemover())->remove($this->directory);
	}

	/**
	 * @throws InvalidResultException
	 */
	public function testWritesAndReadsTheResultsOfAllShards(): void
	{
		$results = new ResultsDirectory(new MeasurementJson());
		$results->write($this->directory . '/tally-1', self::measurement('A::benchA', 1));
		$results->write($this->directory . '/tally-2', self::measurement('A::benchB', 2));
		$results->write($this->directory . '/tally-2', Measurement::failed(CaseId::fromString('A::benchC'), 'It broke.'));

		$this->assertSame(
			['A::benchA' => 1, 'A::benchB' => 2, 'A::benchC' => 'It broke.'],
			self::read($results, $this->directory),
		);
	}

	/**
	 * @throws InvalidResultException
	 */
	public function testTheLastResultOfACaseInPathOrderWins(): void
	{
		$results = new ResultsDirectory(new MeasurementJson());
		$results->write($this->directory . '/b', self::measurement('A::bench', 2));
		$results->write($this->directory . '/a', self::measurement('A::bench', 1));
		$results->write($this->directory . '/c', self::measurement('A::bench', 3));

		$this->assertSame(['A::bench' => 3], self::read($results, $this->directory));
	}

	/**
	 * @throws InvalidResultException
	 */
	public function testSkipsOtherFiles(): void
	{
		$this->assertSame(
			['Tally\Tests\Fixtures\Compare\CompareBench::benchOk' => 1000, 'Tally\Tests\Fixtures\Compare\CompareBench::benchSlower' => 2000],
			self::read(new ResultsDirectory(new MeasurementJson()), __DIR__ . '/../../Fixtures/Results/compare'),
		);
	}

	public function testReportsAFileThatCannotBeWritten(): void
	{
		mkdir($this->directory);
		chmod($this->directory, 0555);

		try {
			(new ResultsDirectory(new MeasurementJson()))->write($this->directory, self::measurement('A::bench', 1));
			self::fail('The result was written.');
		} catch (RuntimeException $e) {
			$this->assertSame('Cannot write ' . $this->directory . '/A-bench-' . substr(md5('A::bench'), 0, 8) . '.json.', $e->getMessage());
		} finally {
			chmod($this->directory, 0755);
		}
	}

	private static function measurement(string $id, int $instructions): Measurement
	{
		return Measurement::measured(CaseId::fromString($id), Metrics::none()->with(Metric::Instructions, $instructions));
	}

	/**
	 * @return array<string, int|string|null> the instructions, or the failure, by case ID
	 * @throws InvalidResultException
	 */
	private static function read(ResultsDirectory $results, string $directory): array
	{
		$read = array_map(static fn (Measurement $measurement): int|string|null => $measurement->failure ?? $measurement->metrics->get(Metric::Instructions), $results->read($directory));
		ksort($read);

		return $read;
	}

}
