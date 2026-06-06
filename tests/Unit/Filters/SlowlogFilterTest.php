<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Filters;

use Switon\Core\App;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ClockInterface;
use Switon\Core\Filesystem;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Http\Event\RequestEnd;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Filter\SlowlogFilter;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\MatcherInterface;
use Switon\Routing\RouterInterface;

use function sys_get_temp_dir;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SlowlogFilterTest extends TestCase
{
    #[Autowired] protected SlowlogFilter $filter;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected FilesystemInterface $filesystem;
    #[Autowired] protected AppInterface $app;
    #[Autowired] protected ClockInterface $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $pathAlias = $this->container->get(PathAliasInterface::class);
        $pathAlias->set('@runtime', sys_get_temp_dir());

        $this->container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));

        $this->container->remove(App::class);
        $this->container->replace(AppInterface::class, ['class' => App::class, 'id' => 'test-app']);

        $this->filesystem = $this->createMock(Filesystem::class);
        $this->container->replace(FilesystemInterface::class, $this->filesystem);

        $this->clock = $this->createMock(ClockInterface::class);
        $this->container->replace(ClockInterface::class, $this->clock);

        // Remove Request and Response if they were already resolved (to force re-injection with new ClockInterface)
        // This is necessary because Request uses ClockInterface in elapsed() method
        if ($this->container->has(RequestInterface::class)) {
            $this->container->remove(RequestInterface::class);
        }
        if ($this->container->has(ResponseInterface::class)) {
            $this->container->remove(ResponseInterface::class);
        }

        // Remove SlowlogFilter if it was already resolved (to force re-injection with new dependencies).
        if ($this->container->has(SlowlogFilter::class)) {
            $this->container->remove(SlowlogFilter::class);
        }

        // Property autowiring is automatically performed by parent::setUp()

        // Get fresh Request and Response instances that use the new ClockInterface
        $this->request = $this->container->get(RequestInterface::class);
        $this->response = $this->container->get(ResponseInterface::class);

        // Ensure SlowlogFilter uses the same Request and Response instances as the test.
        // This is important because SlowlogFilter accesses request properties in onEnd().
        $this->filter = $this->container->get(SlowlogFilter::class);
    }

    protected function setHandler(string $handler): void
    {
        $matcher = $this->createStub(MatcherInterface::class);
        $matcher->method('getHandler')->willReturn($handler);
        $matcher->method('getVariables')->willReturn([]);
        $this->request->getContext()->matcher = $matcher;
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

    public function testOnEndDoesNotLogWhenElapsedTimeIsBelowThreshold(): void
    {
        $startTime = 1000.0;
        $currentTime = 1000.5;

        $this->clock->expects($this->once())
            ->method('microtime')
            ->willReturn($currentTime);

        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_TIME_FLOAT' => $startTime
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $this->filesystem->expects($this->never())
            ->method('append');

        $event = new RequestEnd($this->request, $this->response);
        $this->filter->onEnd($event);
    }

    public function testOnEndLogsWhenElapsedTimeExceedsThreshold(): void
    {
        $startTime = 1000.0;
        $currentTime = 1002.0;

        $this->clock->expects($this->once())
            ->method('microtime')
            ->willReturn($currentTime);

        $requestEvent = $this->createRequestEvent(
            get: ['param' => 'value'],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_TIME_FLOAT' => $startTime,
                'HTTP_HOST' => 'localhost',
                'REQUEST_URI' => '/test?param=value',
                'REMOTE_ADDR' => '127.0.0.1',
                'REQUEST_SCHEME' => 'http'
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $this->setHandler('Controller::action');

        $this->filesystem->expects($this->once())
            ->method('append')
            ->with($this->stringContains('slowlog'), $this->stringContains('GET'));

        // Ensure event uses the same Request and Response instances that SlowlogFilter has.
        // SlowlogFilter uses $this->request and $this->response, but also accesses $event->request.
        $event = new RequestEnd($this->request, $this->response);
        $this->filter->onEnd($event);
    }

    public function testOnEndUsesXResponseTimeHeaderWhenAvailable(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'localhost',
                'REQUEST_URI' => '/test',
                'REMOTE_ADDR' => '127.0.0.1',
                'REQUEST_SCHEME' => 'http'
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $this->setHandler('Controller::action');

        // Set X-Response-Time header to 1.5 seconds (exceeds threshold of 1.0)
        $this->response->setHeader('X-Response-Time', '1.5');

        // Verify response has the header
        $this->assertTrue($this->response->hasHeader('X-Response-Time'), 'Response should have X-Response-Time header');
        $this->assertSame('1.5', $this->response->getHeader('X-Response-Time'), 'X-Response-Time should be 1.5');

        $this->filesystem->expects($this->once())
            ->method('append')
            ->with($this->stringContains('slowlog'), $this->stringContains('1.500'));

        $event = new RequestEnd($this->request, $this->response);
        $this->filter->onEnd($event);
    }
}
