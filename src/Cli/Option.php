<?php declare(strict_types = 1);

namespace PHPStan\Tally\Cli;

final class Option
{

	/**
	 * @param string|null $valueName null for a flag
	 */
	public function __construct(
		public readonly string $name,
		public readonly ?string $valueName,
		public readonly string $description
	)
	{
	}

	public function usage(): string
	{
		return '--' . $this->name . ($this->valueName === null ? '' : '=<' . $this->valueName . '>');
	}

}
