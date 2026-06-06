<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\RequestHandler;

use Switon\Core\App;
use Switon\Http\Server\Adapter\Fpm;
use Switon\Http\Tests\TestCase;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TestableFpmServer extends Fpm
{
    public function formatExceptionForTest(Throwable $throwable): string
    {
        return $this->formatException($throwable);
    }
}

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ExceptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->remove(App::class);
        $this->container->replace(App::class, ['id' => 'test-app']);

        $this->container->replace(\Switon\Http\Server\Adapter\Native\SenderInterface::class, $this->createStub(\Switon\Http\Server\Adapter\Native\SenderInterface::class));
        $this->container->replace(\Switon\Http\ResponseInterface::class, $this->createStub(\Switon\Http\ResponseInterface::class));
        $this->container->replace(\Switon\Routing\RouterInterface::class, $this->createStub(\Switon\Routing\RouterInterface::class));
        $this->container->replace(\Switon\Http\RequestHandlerInterface::class, $this->createStub(\Switon\Http\RequestHandlerInterface::class));
    }

    protected function createServer(): TestableFpmServer
    {
        return $this->container->make(TestableFpmServer::class);
    }

    public function testFormatExceptionFormatsExceptionCorrectly(): void
    {
        $server = $this->createServer();

        $exception = new RuntimeException('Test exception message');
        $result = $server->formatExceptionForTest($exception);

        $this->assertStringContainsString('RuntimeException', $result);
        $this->assertStringContainsString('Test exception message', $result);
        $this->assertStringContainsString('at ', $result);
        $this->assertStringContainsString($exception->getFile(), $result);
        $this->assertStringContainsString((string)$exception->getLine(), $result);
    }

    public function testFormatExceptionHandlesStackTrace(): void
    {
        $server = $this->createServer();

        $exception = new InvalidArgumentException('Invalid argument');
        $result = $server->formatExceptionForTest($exception);

        $this->assertStringNotContainsString('#0 ', $result);
        $this->assertStringContainsString('    at ', $result);
    }
}
