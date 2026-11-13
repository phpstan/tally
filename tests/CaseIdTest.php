<?php declare(strict_types = 1);

namespace Tally;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function array_map;
use function usort;

class CaseIdTest extends TestCase
{

	/**
	 * @return iterable<string, array{string, string, string|null}>
	 */
	public static function dataFromString(): iterable
	{
		yield 'without a data set' => ['App\\FooBench::benchA', 'benchA', null];
		yield 'with a data set' => ['App\\FooBench::benchA@bug-10147.php', 'benchA', 'bug-10147.php'];
		yield 'a data set name can contain @ and ::' => ['App\\FooBench::benchA@a@b::c', 'benchA', 'a@b::c'];
		yield 'an empty data set name' => ['App\\FooBench::benchA@', 'benchA', ''];
	}

	#[DataProvider('dataFromString')]
	public function testFromString(string $id, string $method, ?string $dataSet): void
	{
		$caseId = CaseId::fromString($id);

		$this->assertSame('App\\FooBench', $caseId->class);
		$this->assertSame($method, $caseId->method);
		$this->assertSame($dataSet, $caseId->dataSet);
		$this->assertSame($id, $caseId->toString());
	}

	public function testFromStringRejectsAnIdWithoutAMethod(): void
	{
		$this->expectException(InvalidArgumentException::class);
		CaseId::fromString('App\\FooBench');
	}

	public function testNaturalOrder(): void
	{
		$ids = array_map(static fn (string $id): CaseId => CaseId::fromString($id), [
			'B\\Bench::bench@bug-10147.php',
			'B\\Bench::benchOther',
			'A\\Bench::bench',
			'B\\Bench::bench@bug-1388.php',
			'B\\Bench::bench',
			'B\\Bench::bench@bug-2.php',
		]);
		usort($ids, CaseId::compare(...));

		$this->assertSame([
			'A\\Bench::bench',
			'B\\Bench::bench',
			'B\\Bench::bench@bug-2.php',
			'B\\Bench::bench@bug-1388.php',
			'B\\Bench::bench@bug-10147.php',
			'B\\Bench::benchOther',
		], array_map(static fn (CaseId $id): string => $id->toString(), $ids));
	}

}
