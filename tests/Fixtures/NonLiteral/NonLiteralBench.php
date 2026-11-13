<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\NonLiteral;

use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;

final class NonLiteralBench
{

	private const SMALL = 'small';

	/**
	 * @return iterable<string, array{}>
	 */
	public static function sizes(): iterable
	{
		yield self::SMALL => [];
	}

	#[DataProvider('sizes')]
	#[ExpectedFor(self::SMALL, '1K', '1K', '0B')]
	public function benchSize(): void
	{
	}

}
