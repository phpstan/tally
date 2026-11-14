<?php declare(strict_types = 1);

namespace Tally\Cli;

use Tally\Comparison\Comparator;
use Tally\Discovery\ArgumentResolver;
use Tally\Discovery\AttributeFactory;
use Tally\Discovery\CaseExpander;
use Tally\Discovery\ConstructorArguments;
use Tally\Discovery\DirectorySuiteLoader;
use Tally\Discovery\GrowthPoints;
use Tally\Discovery\ValueTypeCheck;
use Tally\Execution\CaseJobs;
use Tally\Execution\CaseOutcome;
use Tally\Execution\CodSpeedInstallation;
use Tally\Execution\DirectoryRemover;
use Tally\Execution\WorkerCommandLine;
use Tally\Expectation\ExpectationChanges;
use Tally\Expectation\ExpectationFiles;
use Tally\Expectation\ExpectedAttributeRewriter;
use Tally\Expression\Evaluator;
use Tally\Expression\ExpressionErrors;
use Tally\Expression\FunctionLibrary;
use Tally\Expression\Functions\Exponential;
use Tally\Expression\Functions\Factorial;
use Tally\Expression\Functions\Log;
use Tally\Expression\Functions\SquareRoot;
use Tally\Expression\Lexer;
use Tally\Expression\Parser;
use Tally\Expression\TypeChecker;
use Tally\Process\ProcessKiller;
use Tally\Process\ProcessPool;
use Tally\Process\ProcessTable;
use Tally\Report\ConsoleReport;
use Tally\Report\ExpectationsPatch;
use Tally\Report\GitHubAnnotations;
use Tally\Report\MarkdownReport;
use Tally\Report\RelativePath;
use Tally\Report\ReportCommands;
use Tally\Report\Rows;
use Tally\Report\TextTable;
use Tally\Report\UnifiedDiff;
use Tally\Result\CallgrindProfile;
use Tally\Result\MeasurementJson;
use Tally\Result\ResultsDirectory;
use Tally\Runner\CaseRunner;
use Tally\Runner\FailureDescription;
use Tally\Runner\IdleCounter;
use Tally\Runner\PhpMemory;
use Tally\Text\ClosestName;
use function dirname;

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
	 * @param array<string, string> $environment
	 * @param bool $colors whether to color the standard output
	 * @param bool $errorColors whether to color the error output
	 * @param string $phpBinary runs the workers
	 * @param string $tallyBinary the bin/tally the workers run
	 * @param string $codspeed the builds of CodSpeed's Valgrind and tally_hooks for this platform
	 */
	public function create($stdout, $stderr, string $workingDirectory, array $environment, bool $colors, bool $errorColors, string $phpBinary, string $tallyBinary, string $codspeed): Application
	{
		$relativePath = new RelativePath();
		$closestName = new ClosestName();
		$argumentResolver = new ArgumentResolver();
		$constructorArguments = new ConstructorArguments();
		$rewriter = new ExpectedAttributeRewriter();
		$functions = new FunctionLibrary([new Log(), new SquareRoot(), new Exponential(), new Factorial()]);
		$expressionErrors = new ExpressionErrors($closestName);
		$growthPoints = new GrowthPoints(new Parser(new Lexer()), new TypeChecker($functions, $expressionErrors), new Evaluator($functions, $expressionErrors));
		$selectedCases = new SelectedCases(
			new DirectorySuiteLoader(new CaseExpander($rewriter, new AttributeFactory(new ValueTypeCheck()), $argumentResolver, $growthPoints), $constructorArguments, $rewriter, $relativePath),
			$workingDirectory,
		);
		$idle = new IdleCounter();
		$json = new MeasurementJson();
		$comparator = new Comparator();
		$rows = new Rows();
		$resultsArguments = new ResultsArguments(new ResultsDirectory($json));
		$expectationFiles = new ExpectationFiles();
		$expectationChanges = new ExpectationChanges();
		$reportOutput = new ReportOutput(
			new ConsoleReport(new TextTable(), $rows),
			new MarkdownReport($rows),
			new GitHubAnnotations($rows, $relativePath),
			new ReportCommands(),
			$expectationFiles,
			$expectationChanges,
			new ExpectationsPatch(new UnifiedDiff(), $relativePath),
		);

		return new Application(
			new Output($stdout, $stderr, $colors, $errorColors),
			new ArgumentParser($closestName),
			$closestName,
			[
				new ListCommand($selectedCases),
				new RunCommand(
					$selectedCases,
					new CodSpeedInstallation($codspeed),
					new CaseJobs(new WorkerCommandLine($phpBinary, $tallyBinary), new CaseOutcome(), new CallgrindProfile(), $json),
					new ProcessPool(new ProcessKiller(new ProcessTable())),
					$comparator,
					new ProgressFormat(),
					new ResultsDirectory($json),
					$reportOutput,
					new DirectoryRemover(),
					$environment,
					$workingDirectory,
				),
				new CompareCommand($selectedCases, $resultsArguments, $comparator, $reportOutput, $environment, $workingDirectory),
				new AcceptCommand($selectedCases, $resultsArguments, $comparator, $expectationFiles, $expectationChanges),
			],
			[new WorkerCommand(
				$selectedCases,
				new CaseRunner($constructorArguments, $argumentResolver, new PhpMemory(), $idle),
				$idle,
				$json,
				new FailureDescription($relativePath),
				dirname(__DIR__, 2),
				$workingDirectory,
			)],
		);
	}

}
