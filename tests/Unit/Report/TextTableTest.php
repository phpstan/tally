<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Report;

use PHPUnit\Framework\TestCase;
use Tally\Report\TextTable;

final class TextTableTest extends TestCase
{

	public function testPadsByCharactersNotBytes(): void
	{
		$rendered = (new TextTable())->render(
			[['Name', 'Allowed'], ['a', '±5 %'], ['bb', '±10 %']],
			[1],
			'> ',
			static fn (int $row, int $column, string $cell): string => $row === 0 && $column === 0 ? '[' . $cell . ']' : $cell,
		);

		$this->assertSame("> [Name]   Allowed\n> a         ±5 %\n> bb       ±10 %\n", $rendered);
	}

	public function testTheWidestCellIsMultibyte(): void
	{
		$rendered = (new TextTable())->render([['±±±', 'x'], ['ab', 'y']], [], '', static fn (int $row, int $column, string $cell): string => $cell);

		$this->assertSame("±±±   x\nab    y\n", $rendered);
	}

}
