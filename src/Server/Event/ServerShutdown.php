<?php

declare(strict_types=1);

namespace Switon\Http\Server\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Swoole\Http\Server;

/**
 * Event emitted when the server shuts down.
 *
 * Log category: <code>switon.http.server.shutdown</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onShutdown()
 * @see \Switon\Http\Server\Event\ServerBeforeShutdown
 */
#[EventLevel(Severity::NOTICE)]
class ServerShutdown implements JsonSerializable
{
    public function __construct(public Server $server)
    {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'master_pid' => $this->server->master_pid,
        ];
    }
}
