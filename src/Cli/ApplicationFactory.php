<?php declare(strict_types = 1);

namespace Tally\Cli;

use Tally\Discovery\CaseExpander;
use Tally\Discovery\DirectorySuiteLoader;
use Tally\Expectation\ExpectedAttributeRewriter;

/**
 * Builds the application with its services. The only place that decides which implementation
 * of a service the others get.
 */
final class ApplicationFactory
{

	/**
	 * @param resource $stdout
	 * @param resource $stderr
	 * @param string $workingDirectory where tally.php is looked for
	 */
	public function create($stdout, $stderr, string $workingDirectory): Application
	{
		$loader = new DirectorySuiteLoader(new CaseExpander(new ExpectedAttributeRewriter()));

		return new Application(
			new Output($stdout, $stderr),
			new ArgumentParser(),
			[new ListCommand(new SelectedCases($loader, $workingDirectory))],
		);
	}

}
