<?php

declare(strict_types=1);

namespace Switon\Http;

use Switon\Core\Attribute\Autowired;
use Switon\Core\Attribute\Scene;
use Switon\Core\InputInterface;
use Switon\Core\Lazy;
use Switon\Kernel\Kernel as BaseKernel;
use Throwable;

use function console_log;

/**
 * HTTP kernel entry for server startup.
 *
 * Road-signs:
 * - start() bootstraps the app first
 * - set scene=http before startup log
 * - ServerInterface runs the transport loop
 * - RequestHandler owns per-request execution
 * - startup failures go to handleStartupException()
 *
 * @see \Switon\Kernel\Kernel
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\RequestHandlerInterface
 */
#[Scene('http')]
class Kernel extends BaseKernel
{
    /** @var array<string, mixed> */
    protected array $services = [
        InputInterface::class => RequestInterface::class,
    ];

    /**
     * Server runtime entry.
     */
    #[Autowired] protected ServerInterface|Lazy $server;

    /**
     * Bootstrap the app, log startup, then start the HTTP server.
     */
    public function start(): void
    {
        try {
            $this->bootstrap();

            console_log('info', 'Application started: {name} (id={id}, env={env}, debug={debug})', [
                'name' => $this->app->name(),
                'id' => $this->app->id(),
                'env' => $this->app->env(),
                'debug' => $this->app->isDebug() ? 'true' : 'false',
            ]);

            $this->server->start();
        } catch (Throwable $e) {
            $this->handleStartupException($e);
        }
    }
}
