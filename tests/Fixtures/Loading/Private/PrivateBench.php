<?php declare(strict_types = 1);

namespace Tally\Tests\Fixtures\Loading\Private;

final class PrivateBench
{

	public function benchPublic(): void
	{
	}

	private function benchHelper(): void
	{
	}

}
