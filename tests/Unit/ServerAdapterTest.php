<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\App;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Http\RequestHandlerInterface;
use Switon\Http\Server\Adapter\Fpm;
use Switon\Http\Server\Adapter\Native\SenderInterface;
use Switon\Http\Server\Adapter\Php;
use Switon\Http\Server\StaticHandlerInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ServerAdapterTest extends TestCase
{
    #[Autowired] protected AppInterface $app;
    #[Autowired] protected RouterInterface $router;
    #[Autowired] protected ServerInterface $httpServer;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected SenderInterface $sender;

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->container->remove(App::class);
        $this->container->replace(App::class, ['id' => 'test-app']);

        $this->router = $this->createStub(RouterInterface::class);
        $this->container->replace(RouterInterface::class, $this->router);

        $this->httpServer = $this->createStub(ServerInterface::class);
        $this->container->replace(ServerInterface::class, $this->httpServer);

        $this->sender = $this->createMock(SenderInterface::class);
        $this->container->replace(SenderInterface::class, $this->sender);

        // Property autowiring is automatically performed by parent::setUp()
    }

    public function testFpmAdapterClassExists(): void
    {
        $this->assertTrue(class_exists(Fpm::class));
        $this->assertTrue(is_subclass_of(Fpm::class, ServerInterface::class));
    }

    public function testPhpAdapterClassExists(): void
    {
        $this->assertTrue(class_exists(Php::class));
        $this->assertTrue(is_subclass_of(Php::class, ServerInterface::class));
    }

    public function testFpmSendHeadersCallsSenderSendHeaders(): void
    {
        $this->container->replace(RequestHandlerInterface::class, $this->createStub(RequestHandlerInterface::class));

        $this->sender->expects($this->once())
            ->method('sendHeaders');

        $fpm = $this->container->make(Fpm::class);
        $fpm->sendHeaders();
    }

    public function testFpmSendBodyCallsSenderSendBody(): void
    {
        $this->container->replace(RequestHandlerInterface::class, $this->createStub(RequestHandlerInterface::class));

        $this->sender->expects($this->once())
            ->method('sendBody');

        $fpm = $this->container->make(Fpm::class);
        $fpm->sendBody();
    }

    public function testPhpSendHeadersCallsSenderSendHeaders(): void
    {
        $this->container->replace(StaticHandlerInterface::class, $this->createStub(StaticHandlerInterface::class));
        $this->container->replace(RequestHandlerInterface::class, $this->createStub(RequestHandlerInterface::class));

        $this->sender->expects($this->once())
            ->method('sendHeaders');

        $php = $this->container->make(Php::class);
        $php->sendHeaders();
    }

    public function testPhpSendBodyCallsSenderSendBody(): void
    {
        $this->container->replace(StaticHandlerInterface::class, $this->createStub(StaticHandlerInterface::class));
        $this->container->replace(RequestHandlerInterface::class, $this->createStub(RequestHandlerInterface::class));

        $this->sender->expects($this->once())
            ->method('sendBody');

        $php = $this->container->make(Php::class);
        $php->sendBody();
    }
}
