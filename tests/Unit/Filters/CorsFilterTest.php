<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Filters;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\App;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\StopFlow;
use Switon\Http\Event\HeadersSending;
use Switon\Http\Event\RequestBegin;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Filter\CorsFilter;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;
use Switon\Testing\Container;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CorsFilterTest extends TestCase
{
    #[Autowired] protected CorsFilter $filter;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;

    protected function beforeSetUpHttpContainer(): void
    {
        // Set up App BEFORE property autowiring to ensure CorsFilter gets the correct env value.
        $this->container->remove(App::class);
        $this->container->replace(AppInterface::class, ['class' => App::class, 'env' => 'dev']);
    }

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));

        // App is already set in beforeSetUpHttpContainer()
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

    protected function createTestContainer(): Container
    {
        // Use pre-configured test container (ClockInterface, PathAliasInterface, etc. are already registered)
        $container = new Container();

        // Replace ContextManager with test instance
        $container->remove(\Switon\Core\ContextManagerInterface::class);
        $container->replace(\Switon\Core\ContextManagerInterface::class, $this->contextManager);

        // Replace with stubs as needed
        $container->remove(RouterInterface::class);
        $container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $container->remove(ServerInterface::class);
        $container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));
        $container->remove(EventDispatcherInterface::class);
        $container->replace(EventDispatcherInterface::class, $this->createStub(EventDispatcherInterface::class));

        // UrlGeneratorInterface - register implementation (different namespace)
        $container->replace(\Switon\Http\UrlGeneratorInterface::class, \Switon\Http\UrlGenerator::class);

        return $container;
    }

    public function testOnBeginThrowsStopFlowForOptionsRequest(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'OPTIONS']
        );
        $this->request->onRequestReceived($requestEvent);

        $event = new RequestBegin($this->request);

        $this->expectException(StopFlow::class);
        $this->expectExceptionMessage('OPTIONS request handled');
        $this->filter->onBegin($event);
    }

    public function testOnBeginDoesNothingForNonOptionsRequest(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $event = new RequestBegin($this->request);
        $this->filter->onBegin($event);

        $this->assertTrue(true);
    }

    public function testOnHeadersSendingSetsCorsHeadersForCrossOriginRequest(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ORIGIN' => 'https://example.com',
                'HTTP_HOST' => 'localhost'
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $event = new HeadersSending($this->response);
        $this->filter->onResponseHeadersSending($event);

        $this->assertSame('https://example.com', $this->response->getHeader('Access-Control-Allow-Origin'));
        $this->assertSame('true', $this->response->getHeader('Access-Control-Allow-Credentials'));
        $this->assertSame('Origin, Accept, Authorization, Content-Type, X-Requested-With', $this->response->getHeader('Access-Control-Allow-Headers'));
        $this->assertSame('HEAD,GET,POST,PUT,DELETE', $this->response->getHeader('Access-Control-Allow-Methods'));
        $this->assertSame('86400', $this->response->getHeader('Access-Control-Max-Age'));
    }

    public function testOnHeadersSendingDoesNotSetCorsHeadersForSameOriginRequest(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'localhost'
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $event = new HeadersSending($this->response);
        $this->filter->onResponseHeadersSending($event);

        $this->assertSame(null, $this->response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testOnHeadersSendingUsesConfiguredOrigin(): void
    {
        $container = $this->createTestContainer();

        $request = $container->get(RequestInterface::class);
        $response = $container->get(ResponseInterface::class);

        $container->remove(App::class);
        $container->replace(AppInterface::class, ['class' => App::class, 'env' => 'dev']);

        $filter = $container->make(CorsFilter::class, [
            'origin' => 'https://configured.com'
        ]);

        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ORIGIN' => 'https://example.com',
                'HTTP_HOST' => 'localhost'
            ]
        );
        $request->onRequestReceived($requestEvent);

        $event = new HeadersSending($response);
        $filter->onResponseHeadersSending($event);

        $this->assertSame('https://configured.com', $response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testOnHeadersSendingHandlesProductionEnvironmentWithMatchingDomainSuffix(): void
    {
        $container = $this->createTestContainer();

        $request = $container->get(RequestInterface::class);
        $response = $container->get(ResponseInterface::class);

        $container->remove(App::class);
        $container->replace(AppInterface::class, ['class' => App::class, 'env' => 'prod']);

        $filter = $container->make(CorsFilter::class);

        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ORIGIN' => 'https://app.example.com',
                'HTTP_HOST' => 'api.example.com'
            ]
        );
        $request->onRequestReceived($requestEvent);

        $event = new HeadersSending($response);
        $filter->onResponseHeadersSending($event);

        $this->assertSame('https://app.example.com', $response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testOnHeadersSendingUsesWildcardForProductionEnvironmentWithNonMatchingDomain(): void
    {
        $container = $this->createTestContainer();

        $request = $container->get(RequestInterface::class);
        $response = $container->get(ResponseInterface::class);

        $container->remove(App::class);
        $container->replace(AppInterface::class, ['class' => App::class, 'env' => 'prod']);

        $filter = $container->make(CorsFilter::class);

        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ORIGIN' => 'https://example.com',
                'HTTP_HOST' => 'api.test.com'
            ]
        );
        $request->onRequestReceived($requestEvent);

        $event = new HeadersSending($response);
        $filter->onResponseHeadersSending($event);

        $this->assertSame('*', $response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testOnHeadersSendingStripsPortFromHostWhenComparingOrigins(): void
    {
        $container = $this->createTestContainer();

        $request = $container->get(RequestInterface::class);
        $response = $container->get(ResponseInterface::class);

        $container->remove(App::class);
        $container->replace(AppInterface::class, ['class' => App::class, 'env' => 'prod']);

        $filter = $container->make(CorsFilter::class);

        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ORIGIN' => 'https://app.example.com',
                'HTTP_HOST' => 'api.example.com:8443',
            ]
        );
        $request->onRequestReceived($requestEvent);

        $event = new HeadersSending($response);
        $filter->onResponseHeadersSending($event);

        $this->assertSame('https://app.example.com', $response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testOnHeadersSendingSetsAllowCredentialsFalseWhenDisabled(): void
    {
        $container = $this->createTestContainer();

        $request = $container->get(RequestInterface::class);
        $response = $container->get(ResponseInterface::class);

        $container->remove(App::class);
        $container->replace(AppInterface::class, ['class' => App::class, 'env' => 'dev']);

        $filter = $container->make(CorsFilter::class, [
            'credentials' => false,
        ]);

        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ORIGIN' => 'https://other.dev',
                'HTTP_HOST' => 'localhost',
            ]
        );
        $request->onRequestReceived($requestEvent);

        $event = new HeadersSending($response);
        $filter->onResponseHeadersSending($event);

        $this->assertSame('false', $response->getHeader('Access-Control-Allow-Credentials'));
    }
}
