<?php

declare(strict_types=1);

namespace Switon\Http\Server\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Swoole\Http\Server;

/**
 * Event emitted when a task worker process exits with an error.
 *
 * Log category: <code>switon.http.server.tasker.error</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onWorkerError()
 * @see \Switon\Http\Server\Event\ServerTaskerExit
 */
#[EventLevel(Severity::WARNING)]
class ServerTaskerError implements JsonSerializable
{
    public function __construct(
        public Server $server,
        public int    $worker_id,
        public int    $task_id,
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
            'task_id' => $this->task_id,
            'worker_pid' => $this->worker_pid,
            'exit_code' => $this->exit_code,
            'signal' => $this->signal,
        ];
    }
}
