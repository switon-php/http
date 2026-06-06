<?php

declare(strict_types=1);

namespace Switon\Http;

use Switon\Core\ContainerInterface;
use Switon\Core\Exception\InvalidArgumentException;
use Switon\Http\Server\Adapter\Fpm;
use Switon\Http\Server\Adapter\Php;
use Switon\Http\Server\Adapter\Swoole;
use Switon\Http\Server\Detector;

use function extension_loaded;
use function swoole_cpu_num;

/**
 * Registers the ServerInterface factory from ServerOptions.type.
 *
 * Guidance: Choose transport here with ServerOptions.type; runtime request handling belongs to ServerInterface adapters.
 *
 * Road-signs:
 * - entry for server type selection
 * - auto mode delegates to Detector
 * - factory binds ServerInterface
 * - swoole defaults merge here
 * - invalid type exits with InvalidArgumentException
 *
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\ServerOptions
 * @see \Switon\Http\Server\Detector
 * @see \Switon\Http\Server\Adapter\Fpm
 * @see \Switon\Http\Server\Adapter\Php
 * @see \Switon\Http\Server\Adapter\Swoole
 * @see \Switon\Core\Exception\InvalidArgumentException
 */
class Server
{
    public function __invoke(ContainerInterface $container, ServerOptions $serverOptions): mixed
    {
        $allowed = ['auto', 'fpm', 'php', 'swoole'];
        if (!in_array($serverOptions->type, $allowed, true)) {
            InvalidArgumentException::raise('Server type must be one of: {allowed}, got "{type}".', ['allowed' => implode(', ', $allowed), 'type' => $serverOptions->type]);
        }

        $definitions = [
            'default' => '#' . $serverOptions->type,
            'auto' => Detector::detect(),
            'fpm' => ['class' => Fpm::class],
            'php' => ['class' => Php::class],
            'swoole' => ['class' => Swoole::class],
        ];

        $definitions['swoole']['settings'] = $serverOptions->settings + [
                'worker_num' => extension_loaded('swoole') ? swoole_cpu_num() : 1,
                'task_worker_num' => 1,
                'max_request' => 100000,
                'enable_static_handler' => true,
                'http_compression' => false,
            ];

        foreach ($definitions as $name => $definition) {
            $container->set(ServerInterface::class . '#' . $name, $definition);
        }

        $container->set(ServerInterface::class, '#default');

        return $container->get(ServerInterface::class);
    }
}
