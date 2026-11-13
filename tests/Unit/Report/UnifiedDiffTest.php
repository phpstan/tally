<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Report;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tally\Report\UnifiedDiff;

/**
 * The expected diffs are what GNU diff -u prints.
 */
final class UnifiedDiffTest extends TestCase
{

	/**
	 * @return iterable<string, array{string, string, string}>
	 */
	public static function dataRender(): iterable
	{
		yield 'no change' => ["a\nb\n", "a\nb\n", ''];
		yield 'two hunks more than twice the context apart' => [
			"a\nb\nc\nd\ne\nf\ng\nh\ni\nj\nk\nl\nm\nn\n",
			"a\nb\nc\nd\nX\ne\nf\ng\nh\ni\nj\nk\nL\nm\nn\n",
			"--- a/x.php\n+++ b/x.php\n@@ -2,6 +2,7 @@\n b\n c\n d\n+X\n e\n f\n g\n@@ -9,6 +10,6 @@\n i\n j\n k\n-l\n+L\n m\n n\n",
		];
		yield 'one hunk for changes twice the context apart' => [
			"1\n2\n3\n4\n5\n6\n7\n8\n9\n",
			"1\nX\n3\n4\n5\n6\n7\n8\nY\n",
			"--- a/x.php\n+++ b/x.php\n@@ -1,9 +1,9 @@\n 1\n-2\n+X\n 3\n 4\n 5\n 6\n 7\n 8\n-9\n+Y\n",
		];
		yield 'two hunks for changes one line further apart' => [
			"1\n2\n3\n4\n5\n6\n7\n8\n9\n10\n",
			"1\nX\n3\n4\n5\n6\n7\n8\n9\nY\n",
			"--- a/x.php\n+++ b/x.php\n@@ -1,5 +1,5 @@\n 1\n-2\n+X\n 3\n 4\n 5\n@@ -7,4 +7,4 @@\n 7\n 8\n 9\n-10\n+Y\n",
		];
		yield 'a line added at the start' => ["a\nb\nc\n", "Z\na\nb\nc\n", "--- a/x.php\n+++ b/x.php\n@@ -1,3 +1,4 @@\n+Z\n a\n b\n c\n"];
		yield 'a line added at the end' => ["a\nb\nc\nd\ne\nf\ng\nh\n", "a\nb\nc\nd\ne\nf\ng\nh\nX\n", "--- a/x.php\n+++ b/x.php\n@@ -6,3 +6,4 @@\n f\n g\n h\n+X\n"];
		yield 'a line removed' => ["a\nb\nc\n", "a\nc\n", "--- a/x.php\n+++ b/x.php\n@@ -1,3 +1,2 @@\n a\n-b\n c\n"];
		yield 'the only line removed' => ["a\n", '', "--- a/x.php\n+++ b/x.php\n@@ -1 +0,0 @@\n-a\n"];
		yield 'no newline at the end' => [
			"a\nb",
			"a\nb\nc\n",
			"--- a/x.php\n+++ b/x.php\n@@ -1,2 +1,3 @@\n a\n-b\n\\ No newline at end of file\n+b\n+c\n",
		];
		yield 'common lines inside the change' => [
			"1\nA\n2\nB\n3\nC\n4\n",
			"1\nX\n2\nY\n3\nZ\n4\n",
			"--- a/x.php\n+++ b/x.php\n@@ -1,7 +1,7 @@\n 1\n-A\n+X\n 2\n-B\n+Y\n 3\n-C\n+Z\n 4\n",
		];
		yield 'a line moved after the longer common run' => [
			"x\na\nb\nc\ny\n",
			"x\nb\nc\na\ny\n",
			"--- a/x.php\n+++ b/x.php\n@@ -1,5 +1,5 @@\n x\n-a\n b\n c\n+a\n y\n",
		];
		yield 'every line replaced by fewer lines' => [
			"c\nc\nb\nc\n",
			"a\na\n",
			"--- a/x.php\n+++ b/x.php\n@@ -1,4 +1,2 @@\n-c\n-c\n-b\n-c\n+a\n+a\n",
		];
		yield 'a common line kept over an earlier match' => [
			"c\na\na\na\na\n",
			"b\na\na\nb\na\n",
			"--- a/x.php\n+++ b/x.php\n@@ -1,5 +1,5 @@\n-c\n-a\n+b\n a\n a\n+b\n a\n",
		];
		yield 'lines removed after the last common line' => [
			"a\nc\nb\na\na\n",
			"a\nb\n",
			"--- a/x.php\n+++ b/x.php\n@@ -1,5 +1,2 @@\n a\n-c\n b\n-a\n-a\n",
		];
		yield 'two runs swapped' => [
			"x\na\nb\nc\nd\ny\n",
			"x\nc\nd\na\nb\ny\n",
			"--- a/x.php\n+++ b/x.php\n@@ -1,6 +1,6 @@\n x\n-a\n-b\n c\n d\n+a\n+b\n y\n",
		];
		yield 'a changed line among repeated ones' => [
			"x\nx\nx\n",
			"x\ny\nx\n",
			"--- a/x.php\n+++ b/x.php\n@@ -1,3 +1,3 @@\n x\n-x\n+y\n x\n",
		];
	}

	#[DataProvider('dataRender')]
	public function testRender(string $before, string $after, string $diff): void
	{
		$this->assertSame($diff, (new UnifiedDiff())->render('x.php', $before, $after));
	}

}
