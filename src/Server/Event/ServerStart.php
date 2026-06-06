<?php

declare(strict_types=1);

namespace Switon\Http\Server\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Swoole\Http\Server;

/**
 * Event emitted when the server starts.
 *
 * Log category: <code>switon.http.server.start</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onStart()
 * @see \Switon\Http\Server\Event\ServerStarted
 */
#[EventLevel(Severity::NOTICE)]
class ServerStart implements JsonSerializable
{
    public function __construct(public Server $server)
    {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'host' => $this->server->host,
            'port' => $this->server->port,
            'mode' => $this->server->mode,
            'settings' => $this->server->setting,
        ];
    }
}
