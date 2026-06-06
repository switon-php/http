<?php

declare(strict_types=1);

namespace Switon\Http\Server\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Swoole\Http\Server;

/**
 * Event emitted when a task worker process exits.
 *
 * Log category: <code>switon.http.server.tasker.exit</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onWorkerExit()
 * @see \Switon\Http\Server\Event\ServerTaskerError
 */
#[EventLevel(Severity::NOTICE)]
class ServerTaskerExit implements JsonSerializable
{
    public function __construct(public Server $server, public int $worker_id, public int $tasker_id)
    {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'worker_id' => $this->worker_id,
            'tasker_id' => $this->tasker_id,
        ];
    }
}
