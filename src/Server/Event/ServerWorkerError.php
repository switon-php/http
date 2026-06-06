<?php

declare(strict_types=1);

namespace Switon\Http\Server\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Swoole\Http\Server;

/**
 * Event emitted when a worker process exits with an error.
 *
 * Log category: <code>switon.http.server.worker.error</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onWorkerError()
 * @see \Switon\Http\Server\Event\ServerWorkerExit
 */
#[EventLevel(Severity::WARNING)]
class ServerWorkerError implements JsonSerializable
{
    public function __construct(
        public Server $server,
        public int    $worker_id,
        public int    $worker_pid,
        public int    $exit_code,
        public int    $signal
    ) {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'worker_id' => $this->worker_id,
            'worker_pid' => $this->worker_pid,
            'exit_code' => $this->exit_code,
            'signal' => $this->signal,
        ];
    }
}
