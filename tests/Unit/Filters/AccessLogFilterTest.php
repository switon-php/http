<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Filters;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Filesystem;
use Switon\Core\FilesystemInterface;
use Switon\Http\CookiesInterface;
use Switon\Http\Event\RequestEnd;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Filter\AccessLogFilter;
use Switon\Http\Filter\Event\AccessLogWriteFailed;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;
use RuntimeException;

use function file_exists;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AccessLogFilterTest extends TestCase
{
    #[Autowired] protected AccessLogFilter $filter;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected CookiesInterface $cookies;
    protected FilesystemInterface $filesystem; // Not autowired - set manually in beforeSetUpHttpContainer
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    protected string $logFile;

    protected function beforeSetUpHttpContainer(): void
    {
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->container->remove(FilesystemInterface::class);
        $this->container->replace(FilesystemInterface::class, $this->filesystem);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->container->remove(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $this->container->replace(\Psr\EventDispatcher\EventDispatcherInterface::class, $this->eventDispatcher);
        $this->container->remove(\Switon\Eventing\EventDispatcherInterface::class);

        $this->logFile = sys_get_temp_dir() . '/switon_test_' . uniqid() . '.log';

        $this->filter = $this->createFilter();

        $this->container->replace(AccessLogFilter::class, $this->filter);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
        parent::tearDown();
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

    /**
     * @param array<string, mixed> $parameters
     */
    protected function createFilter(array $parameters = []): TestAccessLogFilter
    {
        return $this->container->make(TestAccessLogFilter::class, [
            'file' => $this->logFile,
            'enabled' => true,
            ...$parameters,
        ]);
    }

    public function testOnEndLogsAccessInformationWhenEnabled(): void
    {
        $requestEvent = $this->createRequestEvent(
            get: ['param' => 'value'],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/test?param=value',
                'QUERY_STRING' => 'param=value',
                'HTTP_HOST' => 'localhost',
                'HTTP_USER_AGENT' => 'Test Agent',
                'HTTP_REFERER' => 'https://example.com',
                'REMOTE_ADDR' => '127.0.0.1',
                'REQUEST_TIME_FLOAT' => microtime(true)
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $this->response->setContent('Test response');
        $this->response->setStatus(200);

        $this->filesystem->expects($this->once())
            ->method('append')
            ->with($this->logFile, $this->stringContains('time='));

        $event = new RequestEnd($this->request, $this->response);
        $this->filter->onEnd($event);
    }

    public function testOnEndDoesNotLogWhenDisabled(): void
    {
        $filesystemMock = $this->createMock(Filesystem::class);
        $filesystemMock->expects($this->never())
            ->method('append');

        $this->container->replace(FilesystemInterface::class, $filesystemMock);

        $filter = $this->createFilter(['enabled' => false]);

        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_TIME_FLOAT' => microtime(true)
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $event = new RequestEnd($this->request, $this->response);
        $filter->onEnd($event);
    }

    public function testOnEndDispatchesAccessLogWriteFailedEventOnException(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_TIME_FLOAT' => microtime(true)
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = new RuntimeException('Write failed');
        $this->filesystem->expects($this->once())
            ->method('append')
            ->willThrowException($exception);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof AccessLogWriteFailed
                    && $event->file === $this->logFile
                    && $event->exceptionFile !== null
                    && $event->exceptionLine !== null;
            }));

        $event = new RequestEnd($this->request, $this->response);
        $this->filter->onEnd($event);
    }

    public function testGetVarReturnsCorrectValuesForRequestVariables(): void
    {
        $requestEvent = $this->createRequestEvent(
            get: ['param' => 'value'],
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/test?query=string',
                'QUERY_STRING' => 'query=string',
                'HTTP_HOST' => 'localhost'
            ],
            cookie: ['cookie_name' => 'cookie_value']
        );
        $this->request->onRequestReceived($requestEvent);

        $this->response->setContent('Response content');
        $this->response->setStatus(201);

        $this->filesystem->expects($this->once())
            ->method('append')
            ->with($this->logFile, $this->callback(function ($content) {
                return str_contains($content, 'request_method=POST')
                    && (str_contains($content, '/test') || str_contains($content, 'query=string'))
                    && str_contains($content, 'status=201')
                    && str_contains($content, 'body_bytes_sent=16');
            }));

        $event = new RequestEnd($this->request, $this->response);
        $this->filter->onEnd($event);
    }

    public function testGetVarReturnsCorrectValuesForHttpHeaders(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_USER_AGENT' => 'Test Agent',
                'HTTP_REFERER' => 'https://example.com',
                'HTTP_X_FORWARDED_FOR' => '192.168.1.1',
                'REQUEST_TIME_FLOAT' => microtime(true)
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $this->filesystem->expects($this->once())
            ->method('append')
            ->with($this->logFile, $this->callback(function ($content) {
                return (str_contains($content, 'Test Agent') || str_contains($content, 'http_user_agent'))
                    && (str_contains($content, 'https://example.com') || str_contains($content, 'http_referer'))
                    && (str_contains($content, '192.168.1.1') || str_contains($content, 'http_x_forwarded_for'));
            }));

        $event = new RequestEnd($this->request, $this->response);
        $this->filter->onEnd($event);
    }

    public function testGetVarReturnsCorrectValuesForCookieVariables(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_TIME_FLOAT' => microtime(true)
            ],
            cookie: ['session_id' => 'abc123']
        );
        $this->request->onRequestReceived($requestEvent);

        $this->filesystem->expects($this->once())
            ->method('append')
            ->with($this->logFile, $this->anything());

        $event = new RequestEnd($this->request, $this->response);
        $this->filter->onEnd($event);
    }

    public function testGetVarReturnsCorrectValuesForClientIp(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REMOTE_ADDR' => '192.168.1.100',
                'REQUEST_TIME_FLOAT' => microtime(true)
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $this->filesystem->expects($this->once())
            ->method('append')
            ->with($this->logFile, $this->callback(function ($content) {
                return str_contains($content, 'client_ip=192.168.1.100');
            }));

        $event = new RequestEnd($this->request, $this->response);
        $this->filter->onEnd($event);
    }

    public function testGetVarCoversRequestHandlerUnknownRequestPrefixArgAndServerBranches(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('handler')->willReturn('App\\C::act');
        $request->method('get')->willReturnMap([
            ['page', 'Z', 'p1'],
        ]);
        $request->method('server')->willReturnMap([
            ['REMOTE_ADDR', null, '10.0.0.9'],
            ['REQUEST_TIME_FLOAT', null, microtime(true)],
        ]);

        $cookies = $this->createMock(CookiesInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn('');

        $filter = $this->createFilter([
            'default' => 'Z',
            'request' => $request,
            'cookies' => $cookies,
            'response' => $response,
            'format' => '$request_handler|$request_other|$arg_page|$remote_addr',
        ]);

        $this->assertSame('App\\C::act', $filter->exposeGetVar('request_handler'));
        $this->assertSame('Z', $filter->exposeGetVar('request_other'));
        $this->assertSame('p1', $filter->exposeGetVar('arg_page'));
        $this->assertSame('10.0.0.9', $filter->exposeGetVar('remote_addr'));
    }
}

final class TestAccessLogFilter extends AccessLogFilter
{
    public function exposeGetVar(string $name): string
    {
        return $this->getVar($name);
    }
}
