<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ContainerInterface;
use Switon\Core\InputInterface;
use Switon\Core\LocaleInterface;
use Switon\Core\PathAliasInterface;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Http\CookiesInterface;
use Switon\Http\Filter\RequestLocaleFilter;
use Switon\Http\RequestHandlerInterface;
use Switon\Http\RequestInterface;
use Switon\Http\Server\Listener\LogServerStatusListener;
use Switon\Http\Server\Listener\RenameProcessTitleListener;
use Switon\Http\ServiceProvider;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouteRegistrarInterface;
use Switon\Testing\PackagePathAssert;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ServiceProviderTest extends TestCase
{
    #[Autowired] protected ListenerProviderInterface $listenerProvider;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected CookiesInterface $cookies;
    #[Autowired] protected RenameProcessTitleListener $renameProcessTitleListener;
    #[Autowired] protected LogServerStatusListener $logServerStatusListener;
    #[Autowired] protected RequestHandlerInterface $requestHandler;
    #[Autowired] protected PathAliasInterface $pathAlias;
    #[Autowired] protected ServiceProvider $serviceProvider;

    protected function beforeSetUpHttpContainer(): void
    {
        // Set up ListenerProviderInterface mock BEFORE property autowiring to prevent container from resolving to real ListenerProvider
        // This ensures ServiceProvider (injected in parent::setUp()) gets the mock instead of real ListenerProvider instance
        $this->listenerProvider = $this->createMock(ListenerProviderInterface::class);
        $this->container->remove(ListenerProviderInterface::class);
        $this->container->replace(ListenerProviderInterface::class, $this->listenerProvider);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // ListenerProviderInterface is already set in beforeSetUpHttpContainer()

        $this->cookies = $this->createMock(CookiesInterface::class);
        $this->container->replace(CookiesInterface::class, $this->cookies);

        $this->renameProcessTitleListener = $this->createMock(RenameProcessTitleListener::class);
        $this->container->replace(RenameProcessTitleListener::class, $this->renameProcessTitleListener);

        $this->logServerStatusListener = $this->createMock(LogServerStatusListener::class);
        $this->container->replace(LogServerStatusListener::class, $this->logServerStatusListener);

        $this->requestHandler = $this->createMock(RequestHandlerInterface::class);
        $this->container->replace(RequestHandlerInterface::class, $this->requestHandler);

        // Property autowiring is automatically performed by parent::setUp()
    }

    public function testRegisterSetsInterfaceBindings(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $container->expects($this->once())
            ->method('set')
            ->with(InputInterface::class, RequestInterface::class)
            ->willReturn($container);

        $this->serviceProvider->register($container);
    }

    public function testBootRegistersListenersAndCallsRequestHandlerBoot(): void
    {
        // Re-autowire ServiceProvider to inject the replaced mock dependencies
        $this->injector->inject($this->serviceProvider);
        $resourceRoot = $this->pathAlias->get('@switon.http.resources');
        $this->assertIsString($resourceRoot);
        PackagePathAssert::assertSameAsPackagePath(ServiceProvider::class, $resourceRoot, 'resources');

        $this->listenerProvider->expects($this->exactly(5))
            ->method('register')
            ->willReturnCallback(function ($listener) {
                $expected = [$this->request, $this->cookies, $this->renameProcessTitleListener, $this->logServerStatusListener, RequestLocaleFilter::class];
                $this->assertContains(
                    $listener,
                    $expected,
                    'Listener should be one of the expected listeners'
                );
            });

        $this->requestHandler->expects($this->once())
            ->method('boot');

        $this->serviceProvider->boot();

        $this->assertSame($resourceRoot, $this->pathAlias->get('@switon.http.resources'));
    }

    public function testBootRegistersDefaultPublicAliasWhenAliasWasMissing(): void
    {
        $saved = null;
        if ($this->pathAlias->has('@public')) {
            $saved = $this->pathAlias->get('@public');
            $this->pathAlias->remove('@public');
        }

        try {
            $this->injector->inject($this->serviceProvider);

            $expectedListenerRegistrations = $this->container->has(LocaleInterface::class) ? 5 : 4;
            $this->listenerProvider->expects($this->exactly($expectedListenerRegistrations))
                ->method('register');

            $this->requestHandler->expects($this->once())->method('boot');

            $registrar = $this->container->get(RouteRegistrarInterface::class);
            if ($registrar instanceof \PHPUnit\Framework\MockObject\MockObject) {
                $registrar->expects($this->once())->method('register');
            }

            $this->serviceProvider->boot();

            $this->assertTrue($this->pathAlias->has('@public'));
            $public = $this->pathAlias->get('@public');
            $this->assertIsString($public);
            $this->assertNotSame('', $public);
        } finally {
            $this->pathAlias->remove('@public');
            if ($saved !== null) {
                $this->pathAlias->set('@public', $saved);
            }
        }
    }
}
