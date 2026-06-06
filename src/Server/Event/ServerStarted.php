<?php

declare(strict_types=1);

namespace Switon\Http\Server\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Swoole\Http\Server;

/**
 * Event emitted after the server has started.
 *
 * Road-signs:
 * - emitted from Swoole onStart
 * - carries runtime/version/worker metadata for logs
 * - follows ServerReady in the startup path
 *
 * Log category: <code>switon.http.server.started</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onStart()
 * @see \Switon\Http\Server\Event\ServerReady
 */
#[EventLevel(Severity::INFO)]
class ServerStarted implements JsonSerializable
{
    public function __construct(
        public string $tip,
        public Server $server,
        public float  $elapsed,
        public string $host,
        public int    $port,
        public string $env,
        public string $php_version,
        public string $swoole_version,
        public int    $workers,
        public int    $pid,
    ) {

    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'tip' => $this->tip,
            'elapsed' => $this->elapsed,
            'host' => $this->host,
            'port' => $this->port,
            'env' => $this->env,
            'php_version' => $this->php_version,
            'swoole_version' => $this->swoole_version,
            'workers' => $this->workers,
            'pid' => $this->pid,
        ];
    }
}
