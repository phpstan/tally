<?php declare(strict_types = 1);

namespace Tally\Build\PHPStan;

use Override;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\BetterReflection\Reflection\Adapter\FakeReflectionAttribute;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionAttribute;
use PHPStan\Node\InClassNode;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodRef;
use ShipMonk\PHPStan\DeadCode\Graph\ClassMethodUsage;
use ShipMonk\PHPStan\DeadCode\Graph\UsageOrigin;
use ShipMonk\PHPStan\DeadCode\Provider\MemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;
use Tally\Attribute\After;
use Tally\Attribute\Before;
use Tally\Attribute\DataProvider;
use function is_string;
use function str_ends_with;
use function str_starts_with;

/**
 * Tells the dead code detector about the methods Tally calls through reflection: the benchmark
 * methods of *Bench classes, their data providers, and their #[Before] and #[After] methods.
 */
final class BenchmarkUsageProvider implements MemberUsageProvider
{

	/**
	 * @return list<ClassMethodUsage>
	 */
	#[Override]
	public function getUsages(Node $node, Scope $scope): array
	{
		// @phpstan-ignore phpstanApi.instanceofAssumption
		if (!$node instanceof InClassNode) {
			return [];
		}

		$class = $node->getClassReflection()->getNativeReflection();
		if (!str_ends_with($class->getName(), 'Bench')) {
			return [];
		}

		$used = [];
		foreach ($class->getMethods() as $method) {
			$isBenchmark = $method->isPublic() && !$method->isStatic() && str_starts_with($method->getName(), 'bench');
			if ($isBenchmark || $method->getAttributes(Before::class) !== [] || $method->getAttributes(After::class) !== []) {
				$used[] = $method->getName();
			}
			$used = [...$used, ...$this->arguments($method->getAttributes(DataProvider::class))];
		}

		$usages = [];
		foreach ($used as $method) {
			$usages[] = new ClassMethodUsage(
				UsageOrigin::createVirtual($this, VirtualUsageData::withNote('Called by Tally')),
				new ClassMethodRef($class->getName(), $method, false),
			);
		}

		return $usages;
	}

	/**
	 * @param list<ReflectionAttribute|FakeReflectionAttribute> $attributes
	 * @return list<string> the method names the attributes take
	 */
	private function arguments(array $attributes): array
	{
		$names = [];
		foreach ($attributes as $attribute) {
			foreach ($attribute->getArguments() as $argument) {
				if (!is_string($argument)) {
					continue;
				}

				$names[] = $argument;
			}
		}

		return $names;
	}

}
