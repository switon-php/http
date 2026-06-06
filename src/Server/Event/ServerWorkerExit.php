<?php

declare(strict_types=1);

namespace Switon\Http\Server\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Swoole\Http\Server;

/**
 * Event emitted when a worker process exits.
 *
 * Log category: <code>switon.http.server.worker.exit</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onWorkerExit()
 * @see \Switon\Http\Server\Event\ServerWorkerError
 */
#[EventLevel(Severity::NOTICE)]
class ServerWorkerExit implements JsonSerializable
{
    public function __construct(public Server $server, public int $worker_id, public int $worker_num)
    {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'worker_id' => $this->worker_id,
            'worker_num' => $this->worker_num,
        ];
    }
}
