<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Controller\Attribute;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ClockInterface;
use Switon\Core\StopFlow;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Exception\UnauthorizedException;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Http\UnauthorizedHandler;
use Switon\Http\UrlGeneratorInterface;
use Switon\Routing\RouterInterface;
use RuntimeException;

use function urlencode;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AuthorizeTest extends TestCase
{
    #[Autowired] protected UnauthorizedHandler $handler;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected RouterInterface $router;
    #[Autowired] protected UrlGeneratorInterface $urlGenerator;

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->router = $this->createMock(RouterInterface::class);
        $this->container->replace(RouterInterface::class, $this->router);

        // Set up UrlGeneratorInterface mock AFTER setUpHttpContainer() but BEFORE property autowiring
        // This ensures Response (injected via property autowiring) gets the mock instead of real UrlGenerator
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->container->replace(UrlGeneratorInterface::class, $this->urlGenerator);

        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));
        $this->container->replace(ClockInterface::class, $this->createStub(ClockInterface::class));

        // Property autowiring is automatically performed by parent::setUp()
    }

    protected function createRequestEvent(
        array  $get = [],
        array  $post = [],
        array  $server = [],
        string $rawBody = '',
        array  $cookie = [],
        array  $files = []
    ): RequestReceived {
        return new RequestReceived(
            GET: $get,
            POST: $post,
            SERVER: $server,
            RAW_BODY: $rawBody,
            COOKIE: $cookie,
            FILES: $files
        );
    }

    protected function createUnauthorizedException(): UnauthorizedException
    {
        try {
            UnauthorizedException::raise('Unauthorized');
        } catch (UnauthorizedException $exception) {
            return $exception;
        }
    }

    public function testHandleReturnsFalseForNonUnauthorizedException(): void
    {
        $exception = new RuntimeException('Test exception');
        $result = $this->handler->handle($exception);

        $this->assertFalse($result);
    }

    public function testHandleRedirectsToLoginForNonJsonRequests(): void
    {
        $requestEvent = $this->createRequestEvent(
            get: ['redirect' => '/protected'],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/protected'
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        // Verify urlGenerator->generate() is called with the correct path
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('/login?redirect=' . urlencode('/protected'))
            ->willReturn('/login?redirect=' . urlencode('/protected'));

        $exception = $this->createUnauthorizedException();

        try {
            $this->handler->handle($exception);
            $this->fail('Expected StopFlow exception');
        } catch (StopFlow $e) {
            $this->assertTrue(true);
        }
    }

    public function testHandleReturnsFalseForJsonRequests(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => 'application/json'
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createUnauthorizedException();
        $result = $this->handler->handle($exception);

        $this->assertFalse($result);
    }
}
