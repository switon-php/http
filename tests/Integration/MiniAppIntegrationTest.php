<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Integration;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Http\RequestHandlerInterface;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\Fixtures\MiniAppStyleController;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouteRegistrarInterface;

use function json_decode;

/**
 * Integration tests for simple single-route request handling.
 *
 * Registers a controller with the TestCase container and asserts request → response.
 */
#[AllowMockObjectsWithoutExpectations]
class MiniAppIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableEventDispatching();
    }

    protected function beforeSetUpHttpContainer(): void
    {
        $server = $this->createMock(ServerInterface::class);
        $server->method('write')->willReturn(true);
        $this->container->remove(ServerInterface::class);
        $this->container->replace(ServerInterface::class, $server);
    }

    public function testMiniAppStyleControllerRequestToHelloReturnsExpectedJson(): void
    {
        $this->container->set(MiniAppStyleController::class, $this->container->make(MiniAppStyleController::class));

        $registrar = $this->container->get(RouteRegistrarInterface::class);
        $registrar->registerClass(MiniAppStyleController::class);

        $request = $this->container->get(RequestInterface::class);
        $context = $request->getContext();
        $context->_SERVER = [
            'REQUEST_URI' => '/hello',
            'REQUEST_METHOD' => 'GET',
        ];
        $context->_GET = [];
        $context->_REQUEST = [];

        $handler = $this->container->get(RequestHandlerInterface::class);
        $handler->boot();
        $handler->handle();

        $response = $this->container->get(ResponseInterface::class);
        $content = $response->getContent();

        $this->assertNotEmpty($content);
        $decoded = json_decode((string)$content, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertSame(['message' => 'Hello!'], $decoded['data']);
    }

    public function testMiniAppStyleControllerRequestToGreetWithParameterReturnsExpectedJson(): void
    {
        $this->container->set(MiniAppStyleController::class, $this->container->make(MiniAppStyleController::class));

        $registrar = $this->container->get(RouteRegistrarInterface::class);
        $registrar->registerClass(MiniAppStyleController::class);

        $request = $this->container->get(RequestInterface::class);
        $context = $request->getContext();
        $context->_SERVER = [
            'REQUEST_URI' => '/greet/World',
            'REQUEST_METHOD' => 'GET',
        ];
        $context->_GET = [];
        $context->_REQUEST = [];

        $handler = $this->container->get(RequestHandlerInterface::class);
        $handler->boot();
        $handler->handle();

        $response = $this->container->get(ResponseInterface::class);
        $content = $response->getContent();

        $this->assertNotEmpty($content);
        $decoded = json_decode((string)$content, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertSame(['message' => 'Hello, World!'], $decoded['data']);
    }

}
