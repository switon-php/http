<?php

declare(strict_types=1);

namespace Switon\Http;

use Switon\Core\Attribute\Autowired;
use Switon\Core\Attribute\ResourceAlias;
use Switon\Core\ContainerInterface;
use Switon\Core\InputInterface;
use Switon\Core\Lazy;
use Switon\Core\LocaleInterface;
use Switon\Core\PathAliasInterface;
use Switon\Core\ServiceProviderInterface;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Http\Filter\RequestLocaleFilter;
use Switon\Http\Server\Listener\LogServerStatusListener;
use Switon\Http\Server\Listener\RenameProcessTitleListener;
use Switon\Routing\RouteRegistrarInterface;

/**
 * Registers HTTP contracts, listeners, and startup wiring.
 *
 * Guidance: Keep package-wide startup wiring here; per-request flow starts after RequestHandlerInterface boot and handle.
 *
 * Road-signs:
 * - register HTTP contracts and aliases
 * - boot request and server listeners
 * - LocaleInterface adds RequestLocaleFilter
 * - RequestHandlerInterface boot wires filters and transformers
 * - RouteRegistrarInterface registers controller mappings
 *
 * @see \Switon\Core\ServiceProviderInterface
 * @see \Switon\Http\Server
 * @see \Switon\Http\RequestHandlerInterface
 * @see \Switon\Http\ExceptionDispatcherInterface
 * @see \Switon\Http\Filter\RequestLocaleFilter
 * @see \Switon\Routing\RouteRegistrarInterface
 */
#[ResourceAlias]
class ServiceProvider implements ServiceProviderInterface
{
    #[Autowired] protected ContainerInterface $container;
    #[Autowired] protected ListenerProviderInterface $listenerProvider;

    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected CookiesInterface $cookies;
    #[Autowired] protected RenameProcessTitleListener $renameProcessTitleListener;
    #[Autowired] protected LogServerStatusListener $logServerStatusListener;
    #[Autowired] protected RequestHandlerInterface $requestHandler;
    #[Autowired] protected PathAliasInterface|Lazy $pathAlias;
    #[Autowired] protected RouteRegistrarInterface $routeRegistrar;

    public function register(ContainerInterface $container): void
    {
        $container->set(InputInterface::class, RequestInterface::class);
    }

    public function boot(): void
    {
        // Register core runtime listeners
        $this->listenerProvider->register($this->request);
        $this->listenerProvider->register($this->cookies);
        $this->listenerProvider->register($this->renameProcessTitleListener);
        $this->listenerProvider->register($this->logServerStatusListener);

        if ($this->container->has(LocaleInterface::class)) {
            $this->listenerProvider->register(RequestLocaleFilter::class);
        }

        if (!$this->pathAlias->has('@public')) {
            $this->pathAlias->set('@public', '@root/public');
        }

        $this->requestHandler->boot();

        // Scan and register route mappings
        $this->routeRegistrar->register();
    }
}
