<?php

declare(strict_types=1);

namespace Switon\Http\Server\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Swoole\Http\Server;

/**
 * Event emitted when a client connection closes.
 *
 * Log category: <code>switon.http.server.close</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onClose()
 * @see \Switon\Http\Server\Event\ServerConnect
 */
#[EventLevel(Severity::DEBUG)]
class ServerClose implements JsonSerializable
{
    public function __construct(public Server $server, public int $fd, public int $reactor_id)
    {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'fd' => $this->fd,
            'reactor_id' => $this->reactor_id,
        ];
    }
}
