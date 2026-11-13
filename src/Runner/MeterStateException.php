<?php declare(strict_types = 1);

namespace Tally\Runner;

use LogicException;

/** The meter was used in the wrong order, such as resumed without a pause. */
final class MeterStateException extends LogicException
{

}
