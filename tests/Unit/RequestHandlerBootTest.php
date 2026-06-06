<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Psr\Log\LoggerInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Http\RequestHandlerInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Invoking\InvokerInterface;
use Switon\Rendering\RendererInterface;
use Switon\Routing\RouterInterface;
use RuntimeException;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class RequestHandlerBootTest extends TestCase
{
    #[Autowired] protected RequestHandlerInterface $handler;
    #[Autowired] protected ListenerProviderInterface $listenerProvider;

    protected function beforeSetUpHttpContainer(): void
    {
        // Set up ListenerProviderInterface mock BEFORE property autowiring to prevent container from resolving to real ListenerProvider
        // This ensures RequestHandler (injected in parent::setUp()) gets the mock instead of real ListenerProvider instance
        $this->listenerProvider = $this->createMock(ListenerProviderInterface::class);
        $this->container->remove(ListenerProviderInterface::class);
        $this->container->replace(ListenerProviderInterface::class, $this->listenerProvider);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));
        $this->container->replace(InvokerInterface::class, $this->createStub(InvokerInterface::class));

        // ListenerProviderInterface is already set in beforeSetUpHttpContainer()

        $this->container->replace(LoggerInterface::class, $this->createStub(LoggerInterface::class));
        $this->container->replace(RendererInterface::class, $this->createStub(RendererInterface::class));

        // Property autowiring is automatically performed by parent::setUp()
    }

    public function testBootRegistersFiltersAndTransformers(): void
    {
        $this->listenerProvider->expects($this->exactly(2))
            ->method('register')
            ->with($this->logicalOr(
                $this->isInstanceOf(\Switon\Http\Transformer\NormalizeActionReturnTransformer::class),
                $this->isInstanceOf(\Switon\Http\Filter\RequestIdFilter::class)
            ));

        $this->handler->boot();
    }

    public function testBootThrowsExceptionWhenListenerRegistrationFails(): void
    {
        $this->listenerProvider->expects($this->once())
            ->method('register')
            ->willThrowException(new RuntimeException('register failed'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('register failed');

        $this->handler->boot();
    }
}
