<?php

declare(strict_types=1);

namespace Switon\Http\Exception;

/**
 * Controller class could not be found or loaded.
 *
 * @see \Switon\Http\ControllerMetadata
 * @see \Switon\Http\ControllerScanner
 */
class ControllerNotFoundException extends NotFoundException
{
}
