<?php declare(strict_types = 1);

namespace Tally\Tests\Unit\Comparison;

use PHPUnit\Framework\TestCase;
use Tally\Comparison\Status;
use function array_filter;
use function array_map;
use function array_values;

final class StatusTest extends TestCase
{

	public function testOnlySlowerAndNotMeasuredFail(): void
	{
		$this->assertSame(
			['slower', 'not measured'],
			array_values(array_map(static fn (Status $status): string => $status->value, array_filter(Status::cases(), static fn (Status $status): bool => $status->fails()))),
		);
	}

	public function testWorstIsTheFirstInTheOrderOfTheCases(): void
	{
		$this->assertSame(Status::Ok, Status::worst([]));
		$this->assertSame(Status::Ok, Status::worst([Status::Ok, Status::Ok]));
		$this->assertSame(Status::Faster, Status::worst([Status::Ok, Status::Faster]));
		$this->assertSame(Status::New, Status::worst([Status::Faster, Status::New, Status::Ok]));
		$this->assertSame(Status::NotMeasured, Status::worst([Status::New, Status::NotMeasured]));
		$this->assertSame(Status::Slower, Status::worst([Status::Faster, Status::NotMeasured, Status::Slower]));
	}

}
