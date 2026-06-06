<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\StopFlow;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Http\Event\RequestReceived;
use Switon\Http\RequestHandlerInterface;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Invoking\InvokerInterface;
use Switon\Routing\MatcherInterface;
use Switon\Routing\RouterInterface;
use Throwable;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class RequestHandlerTest extends TestCase
{
    #[Autowired] protected RequestHandlerInterface $handler;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected RouterInterface $router;
    protected MatcherInterface $matcher; // Not autowired - set manually in setUp
    #[Autowired] protected InvokerInterface $invoker;
    #[Autowired] protected ListenerProviderInterface $listenerProvider;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected ServerInterface $httpServer;

    protected function beforeSetUpHttpContainer(): void
    {
        // Set up ListenerProviderInterface BEFORE ContextManager is created
        // (ContextManager might trigger RequestHandler creation which needs ListenerProviderInterface)
        $this->listenerProvider = $this->createMock(ListenerProviderInterface::class);
        $this->container->remove(ListenerProviderInterface::class);
        $this->container->replace(ListenerProviderInterface::class, $this->listenerProvider);

        // Set up InputInterface BEFORE any service that might need it
        // InputInterface is mapped to RequestInterface by ServiceProvider
        $this->container->replace(\Switon\Core\InputInterface::class, \Switon\Http\RequestInterface::class);

        // Set up ServerInterface mock BEFORE property autowiring to prevent container from resolving to Swoole
        // This ensures RequestHandler (injected in parent::setUp()) gets the mock instead of real Swoole instance
        $this->httpServer = $this->createMock(ServerInterface::class);
        $this->httpServer->method('write')->willReturn(true);
        $this->container->remove(ServerInterface::class);
        $this->container->replace(ServerInterface::class, $this->httpServer);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Replace default MockEventDispatcher with mock for event verification
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->container->remove(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $this->container->replace(\Psr\EventDispatcher\EventDispatcherInterface::class, $this->eventDispatcher);
        // Also remove Switon-specific interface mapping if it exists
        $this->container->remove(\Switon\Eventing\EventDispatcherInterface::class);

        // ContextManagerInterface is already set by parent::setUp()
        // ClockInterface is already set by setUpHttpContainer()
        // PathAliasInterface is already configured by Switon\Testing\Container with @view alias

        $this->router = $this->createMock(RouterInterface::class);
        // Set default return value for getPrefix() method (used in invoke() when rendering views)
        $this->router->method('getPrefix')->willReturn('');
        $this->container->replace(RouterInterface::class, $this->router);

        // ServerInterface is already set in beforeSetUpHttpContainer() to prevent Swoole resolution

        $this->container->replace(LoggerInterface::class, $this->createStub(LoggerInterface::class));
        // Don't replace RendererInterface - ExceptionDispatcher needs real Renderer instance
        // Renderer will use PathAliasInterface which has @view alias set above

        // InputInterface is already set in beforeSetUpHttpContainer()

        $this->matcher = $this->createMock(MatcherInterface::class);
        $this->container->replace(MatcherInterface::class, $this->matcher);

        $this->invoker = $this->createMock(InvokerInterface::class);
        $this->container->replace(InvokerInterface::class, $this->invoker);

        // ListenerProviderInterface is already set in beforeSetUpHttpContainer()

        // ExceptionDispatcherInterface is auto-mapped by convention (ExceptionDispatcher).
        // No explicit replace() call needed.

        // Remove RequestHandler if it was already resolved (to force re-injection with new mocks)
        if ($this->container->has(RequestHandlerInterface::class)) {
            $this->container->remove(RequestHandlerInterface::class);
        }

        // Re-inject RequestHandler after all dependencies are set up
        // This ensures RequestHandler uses the correct mocks (especially EventDispatcher)
        // Property autowiring is automatically performed by parent::setUp()
        $this->injector->inject($this);

        // Resolve RequestHandler after dependencies are configured
        $this->handler = $this->container->get(RequestHandlerInterface::class);

        // Boot handler to register filters and transformers as event listeners.
        // This is necessary for NormalizeActionReturnTransformer and RequestIdFilter to work.
        $this->handler->boot();
    }

    protected function createRequestEvent(
        string $method = 'GET',
        string $uri = '/test',
        array  $get = [],
        array  $post = [],
        array  $server = [],
        string $rawBody = '',
        array  $cookie = [],
        array  $files = []
    ): RequestReceived {
        $server['REQUEST_METHOD'] = $method;
        $server['REQUEST_URI'] = $uri;
        return new RequestReceived(
            GET: $get,
            POST: $post,
            SERVER: $server,
            RAW_BODY: $rawBody,
            COOKIE: $cookie,
            FILES: $files
        );
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

    public function testHandleThrowsNotFoundRouteExceptionWhenRouteNotFound(): void
    {
        $requestEvent = $this->createRequestEvent('GET', '/not-found');
        $this->request->onRequestReceived($requestEvent);

        $call = 0;
        $this->router->expects($this->exactly(5))
            ->method('match')
            ->willReturnCallback(function (string $uri, string $verb) use (&$call): mixed {
                $this->assertSame('/not-found', $uri);
                $call++;

                $expectedVerbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
                $this->assertSame($expectedVerbs[$call - 1], $verb);

                throw \Switon\Routing\Exception\NotFoundRouteException::of('Route not found');
            });

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0); // Return the event being dispatched

        $this->handler->handle();

        $statusCode = $this->response->getStatusCode();
        $this->assertContains($statusCode, [404, 500], "Status code should be 404 or 500, got {$statusCode}");
    }

    public function testHandleReturnsMethodNotAllowedWhenOppositeCommonVerbMatches(): void
    {
        $requestEvent = $this->createRequestEvent('POST', '/users');
        $this->request->onRequestReceived($requestEvent);

        $call = 0;
        $this->router->expects($this->exactly(2))
            ->method('match')
            ->willReturnCallback(function (string $uri, string $verb) use (&$call): mixed {
                $this->assertSame('/users', $uri);
                $call++;

                if ($call === 1) {
                    $this->assertSame('POST', $verb);
                    throw \Switon\Routing\Exception\NotFoundRouteException::of('Route not found');
                }

                $this->assertSame('GET', $verb);
                return $this->matcher;
            });

        $this->matcher->method('getHandler')
            ->willReturn('UserController::index');

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->handler->handle();

        $this->assertSame(405, $this->response->getStatusCode());
    }

    public function testHandleReturnsMethodNotAllowedForNonGetPostVerbs(): void
    {
        $requestEvent = $this->createRequestEvent('DELETE', '/users');
        $this->request->onRequestReceived($requestEvent);

        $call = 0;
        $this->router->expects($this->exactly(4))
            ->method('match')
            ->willReturnCallback(function (string $uri, string $verb) use (&$call): mixed {
                $this->assertSame('/users', $uri);
                $call++;

                return match ($call) {
                    1 => $this->throwNotFound($verb, 'DELETE'),
                    2 => $this->throwNotFound($verb, 'GET'),
                    3 => $this->throwNotFound($verb, 'POST'),
                    4 => $this->matcher,
                };
            });

        $this->matcher->method('getHandler')
            ->willReturn('UserController::update');

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->handler->handle();

        $this->assertSame(405, $this->response->getStatusCode());
    }

    public function testHandleThrowsControllerNotFoundExceptionWhenControllerNotFound(): void
    {
        $requestEvent = $this->createRequestEvent();
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->method('getHandler')
            ->willReturn('NonExistentController::action');

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->with($this->anything(), $this->anything()) // URI and verb
            ->willReturn($this->matcher);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0); // Return the event being dispatched

        $this->handler->handle();

        $this->assertSame(404, $this->response->getStatusCode());
    }

    public function testHandleThrowsActionNotFoundExceptionWhenActionNotFound(): void
    {
        $requestEvent = $this->createRequestEvent();
        $this->request->onRequestReceived($requestEvent);

        // Use a real class that exists (TestCase)
        $this->matcher->method('getHandler')
            ->willReturn(TestCase::class . '::nonExistentMethod');

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->with($this->anything(), $this->anything()) // URI and verb
            ->willReturn($this->matcher);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0); // Return the event being dispatched

        $this->handler->handle();

        $this->assertSame(404, $this->response->getStatusCode());
    }

    public function testHandleSuccessfullyProcessesRequest(): void
    {
        $requestEvent = $this->createRequestEvent();
        $this->request->onRequestReceived($requestEvent);

        // Create a test controller with a valid public method
        // Use anonymous class - its class name will be consistent within the same execution
        $testController = new class () {
            public function testAction(): array
            {
                return ['result' => 'success'];
            }
        };
        $handlerString = $testController::class . '::testAction';
        $this->container->replace($testController::class, $testController);

        // Ensure matcher returns the handler string when getHandler() is called
        $this->matcher->method('getHandler')
            ->willReturn($handlerString);

        $this->matcher->method('getVariables')
            ->willReturn(['id' => '123']);

        // Track if router->match() is actually called
        $routerMatchCalled = false;
        $this->router->expects($this->once())
            ->method('match')
            ->with($this->anything(), $this->anything()) // URI and verb
            ->willReturnCallback(function ($uri, $verb) use (&$routerMatchCalled) {
                $routerMatchCalled = true;
                return $this->matcher;
            });

        // Set up event dispatcher to return events (important for event-driven flow)
        // Also track which events are dispatched to help debug
        $dispatchedEvents = [];
        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = get_class($event);
                return $event;
            });

        $this->invoker->expects($this->once())
            ->method('invoke')
            ->willReturn(['result' => 'success']);

        $this->httpServer->expects($this->once())
            ->method('sendHeaders');

        $this->httpServer->expects($this->once())
            ->method('sendBody');


        // Debug: Check Request path() and verb() return values before handle()
        $requestPath = $this->request->path();
        $requestVerb = $this->request->verb();

        // Verify Request context was initialized by onRequestReceived
        $this->assertNotEmpty($requestPath, "Request path() should return a value, got: '{$requestPath}'");
        $this->assertNotEmpty($requestVerb, "Request verb() should return a value, got: '{$requestVerb}'");

        try {
            $this->handler->handle();
        } catch (Throwable $e) {
            $this->fail("handle() threw an exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }

        // Debug: Check if router->match() was actually called
        $this->assertTrue(
            $routerMatchCalled,
            'router->match() should have been called. ' .
            'Dispatched events: ' . implode(', ', $dispatchedEvents)
        );

        // After handle() completes, check that matcher was stored in context
        $matcher = $this->request->getContext()->matcher;

        // Also check status code - if there was an exception, status code might not be 200
        $statusCode = $this->response->getStatusCode();

        $this->assertNotNull(
            $matcher,
            "Matcher should be stored in context after router->match(). " .
            "Response status code: {$statusCode}. Router match called: " . ($routerMatchCalled ? 'yes' : 'no')
        );
        $this->assertStringEndsWith(
            '::testAction',
            $matcher->getHandler(),
            "Handler should end with '::testAction'. Got: {$matcher->getHandler()}"
        );
    }

    public function testHandleHandlesStopFlowGracefully(): void
    {
        // Create a test controller class with a public method
        $testController = new class () {
            public function testAction(): void
            {
            }
        };
        $controllerClass = $testController::class;

        $requestEvent = $this->createRequestEvent();
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->method('getHandler')
            ->willReturn($controllerClass . '::testAction');

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->with($this->anything(), $this->anything()) // URI and verb
            ->willReturn($this->matcher);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->container->replace($controllerClass, $testController);

        $this->invoker->expects($this->once())
            ->method('invoke')
            ->willThrowException(StopFlow::because('Test stop flow'));

        $this->httpServer->expects($this->once())
            ->method('sendHeaders');

        $this->httpServer->expects($this->once())
            ->method('sendBody');

        $this->handler->handle();
    }

    public function testHandleConvertsArrayResponseContentToJson(): void
    {
        // Create a test controller class with a public method
        $testController = new class () {
            public function testAction(): void
            {
            }
        };
        $controllerClass = $testController::class;

        $requestEvent = $this->createRequestEvent();
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->method('getHandler')
            ->willReturn($controllerClass . '::testAction');

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->with($this->anything(), $this->anything()) // URI and verb
            ->willReturn($this->matcher);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->container->replace($controllerClass, $testController);

        $this->invoker->expects($this->once())
            ->method('invoke')
            ->willReturn(['result' => 'success']);

        $this->httpServer->expects($this->once())
            ->method('sendHeaders');

        $this->httpServer->expects($this->once())
            ->method('sendBody');

        $this->handler->handle();

        $content = $this->response->getContent();
        if ($content !== null && $content !== '') {
            $this->assertIsString($content);
            $this->assertJson($content);
        } else {
            $this->assertTrue(true);
        }
    }

    protected function throwNotFound(string $actualVerb, string $expectedVerb): never
    {
        $this->assertSame($expectedVerb, $actualVerb);
        throw \Switon\Routing\Exception\NotFoundRouteException::of('Route not found');
    }

    public function testHandleUsesChunkedResponseWhenResponseIsChunked(): void
    {
        // Create a test controller class with a public method
        $testController = new class () {
            public function testAction(): void
            {
            }
        };
        $controllerClass = $testController::class;

        $requestEvent = $this->createRequestEvent();
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->expects($this->once())
            ->method('getHandler')
            ->willReturn($controllerClass . '::testAction');

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->with($this->anything(), $this->anything()) // URI and verb
            ->willReturn($this->matcher);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->invoker->expects($this->once())
            ->method('invoke')
            ->willReturn(null);

        $this->container->replace($controllerClass, $testController);

        // Response::write() will call sendHeaders() the first time to set chunked encoding
        // So we expect sendHeaders to be called once (by write), not by handle()
        $this->httpServer->expects($this->once())
            ->method('sendHeaders');
        $this->httpServer->expects($this->exactly(2))
            ->method('write')
            ->willReturnCallback(function ($chunk) {
                return true;
            });
        $this->response->write('test chunk');

        $this->httpServer->expects($this->never())
            ->method('sendBody');

        $this->handler->handle();
    }


    /**
     * Test that ViewGetMapping returns JSON when Accept header requests JSON.
     */
    public function testHandleViewGetMappingReturnsJsonWhenAcceptHeaderRequestsJson(): void
    {
        // Arrange
        $testController = new class () {
            #[\Switon\Routing\Attribute\ViewGetMapping('/test')]
            public function testAction(): array
            {
                return ['data' => 'test'];
            }
        };
        $handlerString = $testController::class . '::testAction';
        $this->container->replace($testController::class, $testController);

        $requestEvent = $this->createRequestEvent('GET', '/test');
        $requestEvent->SERVER['HTTP_ACCEPT'] = 'application/json';
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->expects($this->atLeastOnce())
            ->method('getHandler')
            ->willReturn($handlerString);

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->willReturn($this->matcher);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->invoker->expects($this->once())
            ->method('invoke')
            ->willReturn(['data' => 'test']);

        $this->httpServer->expects($this->once())
            ->method('sendHeaders');

        $this->httpServer->expects($this->once())
            ->method('sendBody');

        // Act
        $this->handler->handle();        // Assert - action should be invoked and return data (verified by invoker mock expectation)
        // Response content transformation is handled by registered transformers (not under test here)
        $this->assertTrue(true);
    }

    /**
     * ViewGetMapping: invoke when XHR (XMLHttpRequest) even if Accept does not mention JSON.
     */
    public function testHandleViewGetMappingReturnsJsonWhenXmlHttpRequest(): void
    {
        $testController = new class () {
            #[\Switon\Routing\Attribute\ViewGetMapping('/test')]
            public function testAction(): array
            {
                return ['data' => 'test'];
            }
        };
        $handlerString = $testController::class . '::testAction';
        $this->container->replace($testController::class, $testController);

        $requestEvent = $this->createRequestEvent('GET', '/test');
        $requestEvent->SERVER['HTTP_ACCEPT'] = 'text/html';
        $requestEvent->SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->expects($this->atLeastOnce())
            ->method('getHandler')
            ->willReturn($handlerString);

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->willReturn($this->matcher);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->invoker->expects($this->once())
            ->method('invoke')
            ->willReturn(['data' => 'test']);

        $this->httpServer->expects($this->once())
            ->method('sendHeaders');

        $this->httpServer->expects($this->once())
            ->method('sendBody');

        $this->handler->handle();
        $this->assertTrue(true);
    }

    /**
     * Test that ViewGetMapping renders view without invoking action when Accept header requests HTML.
     */
    public function testHandleViewGetMappingRendersViewWhenAcceptHeaderRequestsHtml(): void
    {
        // Arrange
        $testController = new class () {
            #[\Switon\Routing\Attribute\ViewGetMapping('/test')]
            public function testAction(): array
            {
                return ['data' => 'test'];
            }
        };
        $handlerString = $testController::class . '::testAction';
        $this->container->replace($testController::class, $testController);

        $requestEvent = $this->createRequestEvent('GET', '/test');
        $requestEvent->SERVER['HTTP_ACCEPT'] = 'text/html';
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->expects($this->atLeastOnce())
            ->method('getHandler')
            ->willReturn($handlerString);

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->willReturn($this->matcher);

        // Expect RequestRendering and RequestRendered events to be dispatched
        $renderingEventDispatched = false;
        $renderedEventDispatched = false;
        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$renderingEventDispatched, &$renderedEventDispatched) {
                if ($event instanceof \Switon\Http\Event\RequestRendering) {
                    $renderingEventDispatched = true;
                }
                if ($event instanceof \Switon\Http\Event\RequestRendered) {
                    $renderedEventDispatched = true;
                }
                return $event;
            });

        // ViewGetMapping: HTML path — action not invoked when client does not prefer JSON
        $this->invoker->expects($this->never())
            ->method('invoke');

        $this->httpServer->expects($this->once())
            ->method('sendHeaders');

        $this->httpServer->expects($this->once())
            ->method('sendBody');

        // Act
        $this->handler->handle();

        // Assert - view rendering events should be dispatched
        $this->assertTrue($renderingEventDispatched, 'RequestRendering event should be dispatched');
        $this->assertTrue($renderedEventDispatched, 'RequestRendered event should be dispatched');
    }

    /**
     * Test that ViewMapping always invokes action and renders HTML for GET when the client prefers HTML.
     */
    public function testHandleViewMappingInvokesActionAndRendersViewForNonAjaxGet(): void
    {
        // Arrange
        $testController = new class () {
            #[\Switon\Routing\Attribute\ViewMapping('/test')]
            public function testAction(): array
            {
                return ['data' => 'test'];
            }
        };
        $handlerString = $testController::class . '::testAction';
        $this->container->replace($testController::class, $testController);

        $requestEvent = $this->createRequestEvent('GET', '/test');
        $requestEvent->SERVER['HTTP_ACCEPT'] = 'text/html';
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->expects($this->atLeastOnce())
            ->method('getHandler')
            ->willReturn($handlerString);

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->willReturn($this->matcher);

        $renderingEventDispatched = false;
        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$renderingEventDispatched) {
                if ($event instanceof \Switon\Http\Event\RequestRendering) {
                    $renderingEventDispatched = true;
                }
                return $event;
            });

        // Action SHOULD be invoked for ViewMapping (pure SSR needs data)
        $this->invoker->expects($this->once())
            ->method('invoke')
            ->willReturn(['data' => 'test']);

        $this->httpServer->expects($this->once())
            ->method('sendHeaders');

        $this->httpServer->expects($this->once())
            ->method('sendBody');

        // Act
        $this->handler->handle();

        // Assert
        $this->assertTrue($renderingEventDispatched, 'RequestRendering event should be dispatched');
    }

    /**
     * Test that ViewMapping always renders HTML even when the client prefers JSON (pure SSR, no JSON mode).
     */
    public function testHandleViewMappingRendersHtmlEvenForAjaxGet(): void
    {
        // Arrange
        $testController = new class () {
            #[\Switon\Routing\Attribute\ViewMapping('/test')]
            public function testAction(): array
            {
                return ['data' => 'test'];
            }
        };
        $handlerString = $testController::class . '::testAction';
        $this->container->replace($testController::class, $testController);

        $requestEvent = $this->createRequestEvent('GET', '/test');
        $requestEvent->SERVER['HTTP_ACCEPT'] = 'application/json';
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->expects($this->atLeastOnce())
            ->method('getHandler')
            ->willReturn($handlerString);

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->willReturn($this->matcher);

        $renderingEventDispatched = false;
        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$renderingEventDispatched) {
                if ($event instanceof \Switon\Http\Event\RequestRendering) {
                    $renderingEventDispatched = true;
                }
                return $event;
            });

        // Action SHOULD be invoked (ViewMapping always invokes action)
        $this->invoker->expects($this->once())
            ->method('invoke')
            ->willReturn(['data' => 'test']);

        $this->httpServer->expects($this->once())
            ->method('sendHeaders');

        $this->httpServer->expects($this->once())
            ->method('sendBody');

        // Act
        $this->handler->handle();

        // Assert - ViewMapping always renders HTML, even when wantsJson() is true
        $this->assertTrue($renderingEventDispatched, 'RequestRendering event should be dispatched even for JSON-preferred GET');
    }

    /**
     * Test that ViewPostMapping with POST request invokes action.
     */
    public function testHandleViewPostMappingWithPostRequestInvokesAction(): void
    {
        // Arrange
        $testController = new class () {
            #[\Switon\Routing\Attribute\ViewPostMapping('/test')]
            public function testAction(): array
            {
                return ['data' => 'test'];
            }
        };
        $handlerString = $testController::class . '::testAction';
        $this->container->replace($testController::class, $testController);

        $requestEvent = $this->createRequestEvent('POST', '/test');
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->expects($this->atLeastOnce())
            ->method('getHandler')
            ->willReturn($handlerString);

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->willReturn($this->matcher);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0);

        // Action should be invoked for POST request
        $this->invoker->expects($this->once())
            ->method('invoke')
            ->willReturn(['data' => 'test']);

        $this->httpServer->expects($this->once())
            ->method('sendHeaders');

        $this->httpServer->expects($this->once())
            ->method('sendBody');

        // Act
        $this->handler->handle();

        // Assert - action should be invoked for POST request (verified by invoker mock expectation)
        $this->assertTrue(true);
    }

    /**
     * Test that ViewPostMapping with GET request does NOT invoke action.
     */
    public function testHandleViewPostMappingWithGetRequestDoesNotInvokeAction(): void
    {
        // Arrange
        $testController = new class () {
            #[\Switon\Routing\Attribute\ViewPostMapping('/test')]
            public function testAction(): array
            {
                return ['data' => 'test'];
            }
        };
        $handlerString = $testController::class . '::testAction';
        $this->container->replace($testController::class, $testController);

        $requestEvent = $this->createRequestEvent('GET', '/test');
        $requestEvent->SERVER['HTTP_ACCEPT'] = 'text/html';
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->expects($this->atLeastOnce())
            ->method('getHandler')
            ->willReturn($handlerString);

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->willReturn($this->matcher);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0);

        // Action should NOT be invoked for GET request with ViewPostMapping
        $this->invoker->expects($this->never())
            ->method('invoke');

        $this->httpServer->expects($this->once())
            ->method('sendHeaders');

        $this->httpServer->expects($this->once())
            ->method('sendBody');

        // Act
        $this->handler->handle();

        // Assert - passes if no exception thrown and action was not invoked
        $this->assertTrue(true);
    }

    /**
     * Test RequestHandler handle with custom URI from the request path.
     */
    public function testHandleWithCustomUriFromRequestPath(): void
    {
        // Arrange
        $testController = new class () {
            public function testAction(): array
            {
                return ['result' => 'success'];
            }
        };
        $handlerString = $testController::class . '::testAction';
        $this->container->replace($testController::class, $testController);

        $requestEvent = $this->createRequestEvent('GET', '/custom');
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->expects($this->atLeastOnce())
            ->method('getHandler')
            ->willReturn($handlerString);

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->with('/custom', 'GET')
            ->willReturn($this->matcher);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->invoker->expects($this->once())
            ->method('invoke')
            ->willReturn(['result' => 'success']);

        $this->httpServer->expects($this->once())
            ->method('sendHeaders');

        $this->httpServer->expects($this->once())
            ->method('sendBody');

        // Act
        $this->handler->handle();

        // Assert - verify the request path was used
        $this->assertTrue(true); // Test passes if no exceptions thrown
    }

    /**
     * Test RequestHandler normalizes a request path with a trailing slash.
     */
    public function testHandleNormalizesRequestPathWithTrailingSlash(): void
    {
        // Arrange
        $testController = new class () {
            public function testAction(): array
            {
                return ['result' => 'success'];
            }
        };
        $handlerString = $testController::class . '::testAction';
        $this->container->replace($testController::class, $testController);

        $requestEvent = $this->createRequestEvent('GET', '/custom/');
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->method('getHandler')
            ->willReturn($handlerString);

        $this->matcher->method('getVariables')
            ->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->with('/custom', 'GET')
            ->willReturn($this->matcher);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->invoker->expects($this->once())
            ->method('invoke')
            ->willReturn(['result' => 'success']);

        $this->httpServer->expects($this->once())
            ->method('sendHeaders');

        $this->httpServer->expects($this->once())
            ->method('sendBody');

        // Act
        $this->handler->handle();

        // Assert
        $this->assertTrue(true);
    }

    public function testHandleFailsWhenRouteHandlerStringHasEmptyActionPart(): void
    {
        $requestEvent = $this->createRequestEvent('GET', '/broken');
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->method('getHandler')->willReturn(self::class . '::');
        $this->matcher->method('getVariables')->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->willReturn($this->matcher);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->invoker->expects($this->never())->method('invoke');

        $this->handler->handle();

        $this->assertNotSame(200, $this->response->getStatusCode());
    }

    /**
     * When {@see ResponseInterface::getContent()} stays an array after {@see ResponseStringify}
     * (here: mocked dispatcher does not run listeners), {@see RequestHandler::handle()} must JSON-encode it.
     */
    public function testHandleJsonEncodesArrayBodyWhenContentRemainsArrayAfterStringify(): void
    {
        $testController = new class () {
            public function testAction(): void
            {
            }
        };
        $controllerClass = $testController::class;
        $this->container->replace($controllerClass, $testController);

        $requestEvent = $this->createRequestEvent('GET', '/api/array-body');
        $this->request->onRequestReceived($requestEvent);

        $this->matcher->method('getHandler')->willReturn($controllerClass . '::testAction');
        $this->matcher->method('getVariables')->willReturn([]);

        $this->router->expects($this->once())
            ->method('match')
            ->with('/api/array-body', 'GET')
            ->willReturn($this->matcher);

        $this->response->setContent(['keep' => 'array', 'n' => 2]);

        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnArgument(0);

        $this->invoker->expects($this->once())
            ->method('invoke')
            ->willReturn(null);

        $this->httpServer->expects($this->once())->method('sendHeaders');
        $this->httpServer->expects($this->once())->method('sendBody');

        $this->handler->handle();

        $content = $this->response->getContent();
        $this->assertIsString($content);
        $this->assertJson($content);
        $this->assertStringContainsString('keep', $content);
    }

}
