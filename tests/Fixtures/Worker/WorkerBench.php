<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Worker;

use RuntimeException;
use Tally\Attribute\DataProvider;
use Tally\ScratchDirectory;
use function file_put_contents;
use function range;
use function str_repeat;

final class WorkerBench
{

	public function __construct(private ScratchDirectory $scratch)
	{
	}

	public function benchScratch(): void
	{
		file_put_contents($this->scratch->path() . '/written', 'by the benchmark');
	}

	public function benchEmpty(): void
	{
	}

	public static function numbers(): iterable
	{
		yield 'ten' => [range(1, 10)];
	}

	/** Copies the array of 10, which allocates 376 bytes */
	#[DataProvider('numbers')]
	public function benchCopy(array $numbers): void
	{
		$copy = $numbers;
		$copy[] = 11;
	}

	public function benchAllocate(): void
	{
		$string = str_repeat('x', 100_000);
	}

	public function benchThrows(): void
	{
		throw new RuntimeException('It broke.');
	}

}
