<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Filters;

use Switon\Core\Attribute\Autowired;
use Switon\Http\Event\HeadersSending;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Filter\AppendResponseTimeFilter;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AppendResponseTimeFilterTest extends TestCase
{
    #[Autowired] protected AppendResponseTimeFilter $filter;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));
        // EventDispatcher is already configured by base TestCase

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

    public function testOnHeadersSendingSetsXResponseTimeHeader(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_TIME_FLOAT' => microtime(true) - 0.5
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $event = new HeadersSending($this->response);
        $this->filter->onResponseHeadersSending($event);

        $responseTime = $this->response->getHeader('X-Response-Time');
        $this->assertNotSame(null, $responseTime);
        $this->assertIsString($responseTime);
        $this->assertMatchesRegularExpression('/^\d+\.\d{3}$/', $responseTime);
    }
}
