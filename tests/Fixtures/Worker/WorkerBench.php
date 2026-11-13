<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Worker;

use RuntimeException;
use Tally\ScratchDirectory;
use function file_put_contents;
use function str_repeat;

final class WorkerBench
{

	public function benchScratch(ScratchDirectory $scratch): void
	{
		file_put_contents($scratch->path() . '/written', 'by the benchmark');
	}

	public function benchEmpty(): void
	{
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
