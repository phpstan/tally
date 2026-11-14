<?php declare(strict_types = 1);

namespace Tally\Runner;

use Exception;

/** Counting under CodSpeed is not set up, such as a missing build of the tally_hooks extension. */
final class CodSpeedException extends Exception
{

}
