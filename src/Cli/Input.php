<?php declare(strict_types = 1);

namespace PHPStan\Tally\Cli;

use function is_string;

final class Input
{

	/**
	 * @param array<string, string|true> $options
	 * @param list<string> $arguments
	 */
	public function __construct(private array $options, private array $arguments)
	{
	}

	public function option(string $name): ?string
	{
		$value = $this->options[$name] ?? null;

		return is_string($value) ? $value : null;
	}

	public function flag(string $name): bool
	{
		return isset($this->options[$name]);
	}

	/**
	 * @return list<string>
	 */
	public function arguments(): array
	{
		return $this->arguments;
	}

}
