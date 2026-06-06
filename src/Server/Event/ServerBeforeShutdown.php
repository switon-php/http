<?php

declare(strict_types=1);

namespace Switon\Http\Server\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Swoole\Http\Server;

/**
 * Event emitted before the server shutdown sequence begins.
 *
 * Log category: <code>switon.http.server.before.shutdown</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onBeforeShutdown()
 * @see \Switon\Http\Server\Event\ServerShutdown
 */
#[EventLevel(Severity::NOTICE)]
class ServerBeforeShutdown implements JsonSerializable
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
