<?php declare(strict_types = 1);

namespace Tally\Report;

use Tally\Expectation\ChangePlan;

/**
 * The planned changes of expectations as one patch, which git apply applies in the repository.
 */
final class ExpectationsPatch
{

	public function __construct(private UnifiedDiff $diff, private RelativePath $relativePath)
	{
	}

	/**
	 * @param string $workingDirectory the paths in the patch are relative to it
	 */
	public function render(ChangePlan $plan, string $workingDirectory): string
	{
		$patch = '';
		foreach ($plan->files as $change) {
			$patch .= $this->diff->render($this->relativePath->of($change->file, $workingDirectory), $change->before, $change->after);
		}

		return $patch;
	}

}
