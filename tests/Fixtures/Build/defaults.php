<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Build;

final class Defaults
{

	private int $withDefault = 1;

	private int $withoutDefault;

	private ?int $nullDefault = null, $second;

	public function __construct(private int $promoted = 1, int $required)
	{
		$this->withoutDefault = $required;
		$this->second = $required;
	}

	public function method(int $a, ?string $b = null): void
	{
		$closure = static fn (int $c = 3): int => $c;
		$function = static function (int $d, int ...$rest): void {
		};
	}

}

function plain(int $e = 5): void
{
}
