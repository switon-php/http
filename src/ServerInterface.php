<?php

declare(strict_types=1);

namespace Switon\Http;

/**
 * HTTP server adapter boundary.
 *
 * Guidance: Implement transport details here; request normalization and exception-to-response handling belong to RequestHandler.
 *
 * Road-signs:
 * - Kernel starts the server lifecycle
 * - adapters usually extend AbstractServer
 * - RequestHandler owns the request pipeline
 *
 * @see \Switon\Http\ServerInterface::start()
 * @see \Switon\Http\Kernel
 * @see \Switon\Http\RequestHandler
 * @see \Switon\Http\AbstractServer
 * @see \Switon\Http\Server
 * @see \Switon\Http\Server\Adapter\Fpm
 * @see \Switon\Http\Server\Adapter\Swoole
 */
interface ServerInterface
{
    /**
     * Start accepting HTTP requests.
     */
    public function start(): void;

    /**
     * Send the current response headers.
     */
    public function sendHeaders(): void;

    /**
     * Send the current response body.
     */
    public function sendBody(): void;

    /**
     * Write one chunk in chunked-transfer mode.
     */
    public function write(string $chunk): bool;
}
