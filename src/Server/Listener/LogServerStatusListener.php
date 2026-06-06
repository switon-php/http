<?php

declare(strict_types=1);

namespace Switon\Http\Server\Listener;

use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Json;
use Switon\Eventing\Attribute\EventListener;
use Switon\Eventing\ObservabilityProbe;
use Switon\Http\Server\Event\ServerReady;
use Switon\Http\Server\Event\ServerShutdown;
use Switon\Routing\RouterInterface;

use function console_log;
use function ltrim;

/**
 * Logs server startup and shutdown status events to console observability output.
 *
 * @see \Switon\Http\Server\Event\ServerReady
 * @see \Switon\Http\Server\Event\ServerShutdown
 */
class LogServerStatusListener implements ObservabilityProbe
{
    #[Autowired] protected AppInterface $app;
    #[Autowired] protected RouterInterface $router;

    #[EventListener] public function onServerReady(ServerReady $event): void
    {
        $host = $event->host;
        $port = $event->port;
        $settings = $event->settings;

        $settings = Json::stringify($settings);

        $this->writeStatus('info', 'listen on: {host}:{port} with setting: {settings}', ['host' => $host, 'port' => $port, 'settings' => $settings]);

        $prefix = $this->router->getPrefix();
        $prefix = ltrim($prefix, '?');
        $host = $host === '0.0.0.0' ? '127.0.0.1' : $host;
        /** @noinspection HttpUrlsUsage */
        $this->writeStatus('info', 'http://{host}:{port}{prefix}', ['host' => $host, 'port' => $port, 'prefix' => $prefix]);
    }

    /** @noinspection PhpUnusedParameterInspection */
    #[EventListener] public function onServerShutdown(ServerShutdown $event): void
    {
        $this->writeStatus('info', 'server shutdown');
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function writeStatus(string $level, string $message, array $context = []): void
    {
        console_log($level, $message, $context);
    }
}
