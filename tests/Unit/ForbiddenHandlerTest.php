<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Core\Attribute\Autowired;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Exception\ForbiddenException;
use Switon\Http\Exception\UnauthorizedException;
use Switon\Http\ForbiddenHandler;
use Switon\Http\RequestInterface;
use Switon\Http\Response\JsonRendererInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;
use RuntimeException;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ForbiddenHandlerTest extends TestCase
{
    #[Autowired] protected ForbiddenHandler $handler;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected JsonRendererInterface $jsonRenderer;

    protected function beforeSetUpHttpContainer(): void
    {
        // Set up JsonRendererInterface mock BEFORE property autowiring to prevent container from resolving to real JsonRenderer
        // This ensures Response (injected in parent::setUp()) gets the mock instead of real JsonRenderer instance
        $this->jsonRenderer = $this->createMock(JsonRendererInterface::class);
        $this->container->remove(JsonRendererInterface::class);
        $this->container->replace(JsonRendererInterface::class, $this->jsonRenderer);
    }

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));

        // JsonRendererInterface is already set in beforeSetUpHttpContainer()
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

    protected function createForbiddenException(): ForbiddenException
    {
        try {
            ForbiddenException::raise('Access denied');
        } catch (ForbiddenException $exception) {
            return $exception;
        }
    }

    public function testHandleReturnsFalseForNonForbiddenException(): void
    {
        $exception = new RuntimeException('Test exception');
        $result = $this->handler->handle($exception);

        $this->assertFalse($result);
    }

    public function testHandleReturnsFalseForUnauthorizedException(): void
    {
        $this->assertFalse($this->handler->handle(UnauthorizedException::of('Unauthorized')));
    }

    public function testHandleReturnsJsonResponseForJsonRequests(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => 'application/json'
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $this->jsonRenderer->expects($this->once())
            ->method('render')
            ->with(['code' => 403, 'msg' => 'Access denied to resource.']);

        $exception = $this->createForbiddenException();
        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(403, $this->response->getStatusCode());
    }

    public function testHandleReturnsTextResponseForNonJsonRequests(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createForbiddenException();
        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(403, $this->response->getStatusCode());
        $this->assertSame('Access denied to resource.', $this->response->getContent());
    }

    public function testHandleNonJsonSetsPlainTextContentTypeHeader(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $this->handler->handle($this->createForbiddenException());

        $contentType = $this->response->getHeader('Content-Type');
        $this->assertNotNull($contentType);
        $this->assertStringStartsWith('text/plain', $contentType);
        $this->assertStringContainsString('charset=utf-8', $contentType);
    }

    public function testHandleDetectsJsonViaXmlHttpRequestHeader(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $this->jsonRenderer->expects($this->once())
            ->method('render')
            ->with(['code' => 403, 'msg' => 'Access denied to resource.']);

        $result = $this->handler->handle($this->createForbiddenException());

        $this->assertTrue($result);
        $this->assertSame(403, $this->response->getStatusCode());
    }
}
