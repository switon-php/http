<?php

declare(strict_types=1);

namespace Switon\Http\Exception;

use Switon\Http\Exception;

/**
 * Controller definition is invalid for HTTP routing.
 *
 * @see \Switon\Http\Exception
 * @see \Switon\Http\ControllerMetadata
 * @see \Switon\Routing\Attribute\RequestMapping
 */
class InvalidControllerException extends Exception
{
}
