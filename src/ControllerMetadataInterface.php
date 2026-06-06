<?php

declare(strict_types=1);

namespace Switon\Http;

/**
 * Controller action metadata resolver.
 *
 * Guidance: Use this after controller discovery; it resolves action names and URL paths, not class scanning.
 *
 * Road-signs:
 * - getActions() lists action methods for one controller
 * - getPath() maps one controller action to its HTTP path
 * - controller discovery belongs to \Switon\Routing\ControllerScannerInterface
 *
 * @see \Switon\Http\ControllerMetadata
 * @see \Switon\Routing\ControllerScannerInterface
 * @see \Switon\Routing\Attribute\MappingInterface
 * @see \Switon\Routing\Attribute\RequestMapping
 */
interface ControllerMetadataInterface
{
    /**
     * @param class-string $controller
     *
     * @return list<string>
     * Return action method names for one controller class.
     */
    public function getActions(string $controller): array;

    /**
     * @param class-string $controller
     * Resolve the URL path for one controller action.
     */
    public function getPath(string $controller, string $action): string;
}
