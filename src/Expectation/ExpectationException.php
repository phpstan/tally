<?php declare(strict_types = 1);

namespace Tally\Expectation;

use Exception;

/**
 * The expectations of a case cannot be found or rewritten, such as an attribute written in a way
 * tally accept does not understand. An ExpectationSource of a data provider throws it too.
 *
 * @api
 */
final class ExpectationException extends Exception
{

}
