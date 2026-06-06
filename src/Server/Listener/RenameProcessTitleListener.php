<?php

declare(strict_types=1);

namespace Switon\Http\Server\Listener;

use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Eventing\Attribute\EventListener;
use Switon\Eventing\ObservabilityProbe;
use Switon\Http\Server\Event\ServerManagerStart;
use Switon\Http\Server\Event\ServerStart;
use Switon\Http\Server\Event\ServerWorkerStart;

use function function_exists;
use function putenv;
use function sprintf;

/**
 * Renames Swoole process titles for master, manager, and worker observability.
 *
 * @see \Switon\Http\Server\Event\ServerStart
 * @see \Switon\Http\Server\Event\ServerManagerStart
 * @see \Switon\Http\Server\Event\ServerWorkerStart
 */
class RenameProcessTitleListener implements ObservabilityProbe
{
    #[Autowired] protected AppInterface $app;

    /** @noinspection PhpUnusedParameterInspection */
    #[EventListener] public function onServerStart(ServerStart $event): void
    {
        $this->setProcessTitle(sprintf('%s.swoole-master', $this->app->id()));
    }

    /** @noinspection PhpUnusedParameterInspection */
    #[EventListener] public function onServerManagerStart(ServerManagerStart $event): void
    {
        $this->setProcessTitle(sprintf('%s.swoole-manager', $this->app->id()));
    }

    #[EventListener] public function onServerWorkerStart(ServerWorkerStart $event): void
    {
        $worker_num = $event->worker_num;
        $worker_id = $event->worker_id;

        putenv('SNOWFLAKE_MACHINE_ID=' . $worker_id);

        if ($worker_id < $worker_num) {
            $this->setProcessTitle(sprintf('%s.swoole-worker.%d', $this->app->id(), $worker_id));
        } else {
            $tasker_id = $worker_id - $worker_num;
            $this->setProcessTitle(sprintf('%s.swoole-worker.%d.%d', $this->app->id(), $worker_id, $tasker_id));
        }
    }

    protected function setProcessTitle(string $title): void
    {
        if (!function_exists('cli_set_process_title')) {
            return;
        }

        cli_set_process_title($title);
    }
}
