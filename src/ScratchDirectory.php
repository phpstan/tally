<?php declare(strict_types = 1);

namespace Tally;

/**
 * An empty directory for one case, such as for a cache the benchmarked code writes. A benchmark,
 * before method, or after method gets it by declaring a parameter of this type. When CodSpeed
 * counts the case, the native run before it uses the same directory, so the counted run finds
 * what the native run left there.
 *
 * @api
 */
final class ScratchDirectory
{

	public function __construct(private string $path)
	{
	}

	public function path(): string
	{
		return $this->path;
	}

}
