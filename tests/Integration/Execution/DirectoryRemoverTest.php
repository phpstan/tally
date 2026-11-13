<?php declare(strict_types = 1);

namespace Tally\Tests\Integration\Execution;

use Override;
use PHPUnit\Framework\TestCase;
use Tally\Execution\DirectoryRemover;
use function file_put_contents;
use function getmypid;
use function mkdir;
use function symlink;
use function sys_get_temp_dir;

final class DirectoryRemoverTest extends TestCase
{

	private string $directory;

	#[Override]
	protected function setUp(): void
	{
		$this->directory = sys_get_temp_dir() . '/tally-remover-' . getmypid();
		mkdir($this->directory . '/outside/kept', 0777, true);
		file_put_contents($this->directory . '/outside/kept/file', 'kept');
	}

	#[Override]
	protected function tearDown(): void
	{
		(new DirectoryRemover())->remove($this->directory);
	}

	public function testRemovesNestedFilesAndLinksButNotWhatTheyPointTo(): void
	{
		$work = $this->directory . '/work';
		mkdir($work . '/case/scratch/nested', 0777, true);
		file_put_contents($work . '/case/native.log', 'log');
		file_put_contents($work . '/case/scratch/nested/.hidden', 'cache');
		symlink($this->directory . '/outside', $work . '/case/scratch/link');
		symlink($this->directory . '/outside/kept/file', $work . '/case/file-link');

		(new DirectoryRemover())->remove($work);

		$this->assertDirectoryDoesNotExist($work);
		$this->assertStringEqualsFile($this->directory . '/outside/kept/file', 'kept');
	}

	public function testAMissingDirectoryIsNothingToRemove(): void
	{
		(new DirectoryRemover())->remove($this->directory . '/missing');

		$this->assertDirectoryDoesNotExist($this->directory . '/missing');
		$this->assertStringEqualsFile($this->directory . '/outside/kept/file', 'kept');
	}

}
