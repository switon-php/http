<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Filters;

use Switon\Core\Attribute\Autowired;
use Switon\Http\Event\HeadersSending;
use Switon\Http\Event\RequestBegin;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Filter\RequestIdFilter;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class RequestIdFilterTest extends TestCase
{
    #[Autowired] protected RequestIdFilter $filter;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));

        // Property autowiring is automatically performed by parent::setUp()
    }

    protected function createRequestEvent(
        string $method = 'GET',
        array  $get = [],
        array  $post = [],
        array  $server = [],
        string $rawBody = '',
        array  $cookie = [],
        array  $files = []
    ): RequestReceived {
        $server['REQUEST_METHOD'] = $method;
        return new RequestReceived(
            GET: $get,
            POST: $post,
            SERVER: $server,
            RAW_BODY: $rawBody,
            COOKIE: $cookie,
            FILES: $files
        );
    }

    public function testOnBeginGeneratesRequestIdWhenNotPresent(): void
    {
        $requestEvent = $this->createRequestEvent();
        $this->request->onRequestReceived($requestEvent);

        $event = new RequestBegin($this->request);
        $this->filter->onBegin($event);

        $requestId = $this->request->header('x-request-id');
        $this->assertNotSame(null, $requestId);
        $this->assertIsString($requestId);
        $this->assertNotSame([], $requestId);
    }

    public function testOnBeginDoesNotOverrideExistingRequestId(): void
    {
        $requestEvent = $this->createRequestEvent();
        $this->request->onRequestReceived($requestEvent);

        $existingId = 'existing-request-id';
        $this->request->getContext()->headers['x-request-id'] = $existingId;

        $event = new RequestBegin($this->request);
        $this->filter->onBegin($event);

        $this->assertSame($existingId, $this->request->header('x-request-id'));
    }

    public function testOnHeadersSendingSetsXRequestIdHeader(): void
    {
        $requestEvent = $this->createRequestEvent();
        $this->request->onRequestReceived($requestEvent);

        $requestId = 'test-request-id';
        $this->request->getContext()->headers['x-request-id'] = $requestId;

        $event = new HeadersSending($this->response);
        $this->filter->onResponseHeadersSending($event);

        $this->assertSame($requestId, $this->response->getHeader('X-Request-Id'));
    }

    public function testOnHeadersSendingDoesNotSetHeaderWhenRequestIdIsNull(): void
    {
        $requestEvent = $this->createRequestEvent();
        $this->request->onRequestReceived($requestEvent);

        $event = new HeadersSending($this->response);
        $this->filter->onResponseHeadersSending($event);

        $this->assertSame(null, $this->response->getHeader('X-Request-Id'));
    }
}
