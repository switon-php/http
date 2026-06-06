<?php

declare(strict_types=1);

namespace Switon\Http\Server;

/**
 * Defines static-asset detection and file resolution for server adapters.
 *
 * Guidance: Use this only for direct asset serving; dynamic request routing belongs to RouterInterface.
 *
 * Road-signs:
 * - isFile() checks whether the URI should be treated as a static asset
 * - getFile() resolves the final filesystem path
 * - getMimeType() maps a resolved file to a response content type
 *
 * @see \Switon\Http\Server\StaticHandler
 * @see \Switon\Http\Event\AssetSending
 * @see \Switon\Http\Event\AssetSent
 * @see \Switon\Http\Server\Adapter\Php
 * @see \Switon\Http\Server\Adapter\Swoole
 */
interface StaticHandlerInterface
{
    /**
     * Check whether the URI targets a static asset.
     */
    public function isFile(string $uri): bool;

    /**
     * Resolve the filesystem path for a static asset URI.
     */
    public function getFile(string $uri): ?string;

    /**
     * Resolve MIME type for a static file path.
     */
    public function getMimeType(string $file): string;
}
