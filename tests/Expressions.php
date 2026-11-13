<?php declare(strict_types = 1);

namespace Tally\Tests;

use Tally\Discovery\GrowthPoints;
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
use Tally\Text\ClosestName;

/** The expression language as ApplicationFactory composes it */
final class Expressions
{

	public function parser(): Parser
	{
		return new Parser(new Lexer());
	}

	public function evaluator(): Evaluator
	{
		return new Evaluator($this->functions(), $this->errors());
	}

	public function checker(): TypeChecker
	{
		return new TypeChecker($this->functions(), $this->errors());
	}

	public function growthPoints(): GrowthPoints
	{
		return new GrowthPoints($this->parser(), $this->checker(), $this->evaluator());
	}

	private function functions(): FunctionLibrary
	{
		return new FunctionLibrary([new Log(), new SquareRoot(), new Exponential(), new Factorial()]);
	}

	private function errors(): ExpressionErrors
	{
		return new ExpressionErrors(new ClosestName());
	}

}
