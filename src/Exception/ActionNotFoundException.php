<?php

declare(strict_types=1);

namespace Switon\Http\Exception;

/**
 * Action method not found on the resolved controller.
 *
 * @see \Switon\Http\ControllerMetadata
 * @see \Switon\Routing\Attribute\MappingInterface
 */
class ActionNotFoundException extends NotFoundException
{
}
