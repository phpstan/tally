<?php declare(strict_types = 1);

namespace PHPStan\Tally\Cli;

use RuntimeException;

/** Wrong command-line arguments or configuration, reported with the command's usage. */
final class UsageException extends RuntimeException
{

}
