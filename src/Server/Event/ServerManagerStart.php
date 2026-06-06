<?php

declare(strict_types=1);

namespace Switon\Http\Server\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Swoole\Http\Server;

/**
 * Event emitted when the manager process starts.
 *
 * Log category: <code>switon.http.server.manager.start</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onManagerStart()
 * @see \Switon\Http\Server\Event\ServerManagerStop
 */
#[EventLevel(Severity::DEBUG)]
class ServerManagerStart implements JsonSerializable
{
    public function __construct(public Server $server)
    {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'manager_pid' => $this->server->manager_pid,
        ];
    }
}
