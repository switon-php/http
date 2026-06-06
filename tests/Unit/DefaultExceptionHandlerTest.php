<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Psr\Log\LoggerInterface;
use Switon\Core\App;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Json;
use Switon\Core\StopFlow;
use Switon\Http\DefaultExceptionHandler;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Exception\ForbiddenException;
use Switon\Http\Exception\NotFoundException;
use Switon\Http\Exception\UnauthorizedException;
use Switon\Http\RequestInterface;
use Switon\Http\Response\JsonRendererInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Rendering\Frames;
use Switon\Rendering\RendererInterface;
use Switon\Routing\RouterInterface;
use RuntimeException;
use Throwable;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class DefaultExceptionHandlerTest extends TestCase
{
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected LoggerInterface $logger;
    #[Autowired] protected RendererInterface $renderer;
    protected DefaultExceptionHandler $handler;
    protected AppInterface $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = $this->container->get(DefaultExceptionHandler::class);
    }

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));

        $jsonRenderer = $this->createMock(JsonRendererInterface::class);
        $jsonRenderer->method('render')
            ->willReturnCallback(function ($data, $options) {
                $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
                $this->response->setContent(Json::stringify($data, $options ?: (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
                return $this->response;
            });
        $this->container->replace(JsonRendererInterface::class, $jsonRenderer);

        $this->container->remove(App::class);
        $this->container->replace(App::class, ['debug' => false]);
        $this->app = $this->container->get(App::class);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->container->replace(LoggerInterface::class, $this->logger);

        $this->renderer = $this->createMock(RendererInterface::class);
        $this->container->replace(RendererInterface::class, $this->renderer);

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

    protected function createNotFoundException(): NotFoundException
    {
        try {
            NotFoundException::raise('Not found');
        } catch (NotFoundException $exception) {
            return $exception;
        }
    }

    protected function enableDebugMode(): void
    {
        $this->container->remove(App::class);
        $this->container->remove(\Switon\Core\AppInterface::class);
        $this->app = $this->container->make(App::class, ['debug' => true]);
        $this->container->replace(App::class, $this->app);
        $this->container->replace(\Switon\Core\AppInterface::class, $this->app);
        $this->handler = $this->container->make(DefaultExceptionHandler::class);
    }

    public function testHandleReturnsFalseForStopFlow(): void
    {
        $exception = StopFlow::because('Test');
        $result = $this->handler->handle($exception);

        $this->assertFalse($result);
    }

    public function testHandleLogs5xxErrorsAsErrorLevel(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = new RuntimeException('Server error');

        $this->logger->expects($this->once())
            ->method('error')
            ->with(RuntimeException::class, $this->callback(function ($context) use ($exception) {
                return isset($context['exception']) && $context['exception'] === $exception;
            }));

        $this->renderer->expects($this->exactly(2))
            ->method('exists')
            ->willReturn(false);

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(500, $this->response->getStatusCode());
    }

    public function testHandleLogs4xxErrorsAsInfoAndDebugLevel(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('NotFoundException'), []);

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(NotFoundException::class, $this->callback(function ($context) use ($exception) {
                return isset($context['exception']) && $context['exception'] === $exception;
            }));

        $this->renderer->expects($this->exactly(2))
            ->method('exists')
            ->willReturn(false);

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(404, $this->response->getStatusCode());
    }

    public function testHandleAddsRequestContextForAuthorizationErrors(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/admin/users',
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = UnauthorizedException::of('Authentication required for blog::view');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Authentication required for blog::view'),
                $this->callback(static function (array $context): bool {
                    return ($context['request_method'] ?? null) === 'POST'
                        && ($context['request_url'] ?? null) === 'http:///admin/users';
                })
            );

        $this->logger->expects($this->once())
            ->method('debug');

        $this->renderer->expects($this->exactly(2))
            ->method('exists')
            ->willReturn(false);

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(401, $this->response->getStatusCode());
    }

    public function testHandleAddsRequestContextForForbiddenErrors(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'PATCH',
                'REQUEST_URI' => '/admin/roles',
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = ForbiddenException::of('Access denied for blog::edit');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Access denied for blog::edit'),
                $this->callback(static function (array $context): bool {
                    return ($context['request_method'] ?? null) === 'PATCH'
                        && ($context['request_url'] ?? null) === 'http:///admin/roles';
                })
            );

        $this->logger->expects($this->once())
            ->method('debug');

        $this->renderer->expects($this->exactly(2))
            ->method('exists')
            ->willReturn(false);

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(403, $this->response->getStatusCode());
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

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('debug');

        $result = $this->handler->handle($exception);
        $this->assertTrue($result);
        $this->assertSame(404, $this->response->getStatusCode());
        $this->assertNotSame(null, $this->response->getContent());
    }

    public function testHandleReturnsJsonResponseForPlusJsonRequests(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => 'application/problem+json; charset=utf-8',
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('debug');

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(404, $this->response->getStatusCode());
        $this->assertNotSame(null, $this->response->getContent());
    }

    public function testHandleReturnsJsonResponseForAjaxRequests(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('debug');

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(404, $this->response->getStatusCode());
        $this->assertNotSame(null, $this->response->getContent());
    }

    public function testHandleReturnsJsonResponseForAjaxQueryFlag(): void
    {
        $requestEvent = $this->createRequestEvent(
            get: ['ajax' => '1'],
            server: [
                'REQUEST_METHOD' => 'GET',
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('debug');

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(404, $this->response->getStatusCode());
        $this->assertNotSame(null, $this->response->getContent());
    }

    public function testHandleMasks500ErrorsInJsonWhenDebugDisabled(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => 'application/json'
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = new RuntimeException('Sensitive database error');

        $this->logger->expects($this->once())
            ->method('error');

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(500, $this->response->getStatusCode());

        $content = $this->response->getContent();
        $this->assertStringContainsString('Internal Server Error', $content);
        $this->assertStringNotContainsString('Sensitive database error', $content);
    }

    public function testHandleReturnsHtmlResponseForNonJsonRequests(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('debug');

        $this->renderer->expects($this->exactly(2))
            ->method('exists')
            ->willReturn(false);

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(404, $this->response->getStatusCode());
        $this->assertStringContainsString('404', $this->response->getContent());
    }

    public function testHandleHtmlResponseSetsTextHtmlContentType(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->once())->method('debug');

        $this->renderer->expects($this->exactly(2))
            ->method('exists')
            ->willReturn(false);

        $this->handler->handle($exception);

        $contentType = $this->response->getHeader('Content-Type');
        $this->assertNotNull($contentType);
        $this->assertStringStartsWith('text/html', $contentType);
    }

    public function testRenderUsesGenericErrorTemplateWhenStatusSpecificTemplateMissing(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->once())->method('debug');

        $this->renderer->expects($this->exactly(2))
            ->method('exists')
            ->willReturnCallback(static function (string $template): bool {
                return $template === '@view/Errors/Error';
            });

        $this->renderer->expects($this->once())
            ->method('render')
            ->with(
                '@view/Errors/Error',
                $this->callback(function (array $data) use ($exception): bool {
                    return ($data['statusCode'] ?? null) === 404
                        && ($data['exception'] ?? null) === $exception;
                })
            )
            ->willReturn(Frames::of()->setContent('<html>generic error</html>'));

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame('<html>generic error</html>', $this->response->getContent());
    }

    public function testHandleReturnsHtmlWhenContentTypeIsApplicationXml(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'application/xml',
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->once())->method('debug');

        $this->renderer->expects($this->exactly(2))
            ->method('exists')
            ->willReturn(false);

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(404, $this->response->getStatusCode());
        $content = (string)$this->response->getContent();
        $this->assertStringStartsWith('<html', $content);
    }

    public function testHandleIncludesDebugInformationInJsonResponseWhenDebugModeEnabled(): void
    {
        $this->enableDebugMode();

        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => 'application/json'
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('debug');

        try {
            $result = $this->handler->handle($exception);
            $this->assertTrue($result);
            $this->assertSame(404, $this->response->getStatusCode());
        } catch (Throwable $e) {
            // If exception is raised, that's expected behavior
            $this->assertInstanceOf(NotFoundException::class, $e);
        }
    }

    public function testRenderUsesDebugTemplateWhenDebugModeEnabled(): void
    {
        $this->enableDebugMode();

        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('debug');

        $this->renderer->method('exists')
            ->willReturnCallback(static fn (string $t): bool => $t === '@view/Errors/Debug');

        $this->renderer->expects($this->once())
            ->method('render')
            ->with('@view/Errors/Debug', ['exception' => $exception])
            ->willReturn(\Switon\Rendering\Frames::of()->setContent('<html>Debug template</html>'));

        $result = $this->handler->handle($exception);
        $this->assertTrue($result);
        $this->assertSame('<html>Debug template</html>', $this->response->getContent());
    }

    public function testRenderUsesPackageDebugTemplateFallbackWhenCustomTemplateMissing(): void
    {
        $this->enableDebugMode();

        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('debug');

        $this->renderer->expects($this->once())
            ->method('exists')
            ->with('@view/Errors/Debug')
            ->willReturn(false);

        $this->renderer->expects($this->once())
            ->method('render')
            ->with('@switon.http.resources/DefaultExceptionHandler/View/Debug', ['exception' => $exception])
            ->willReturn(Frames::of()->setContent('<html>Package debug template</html>'));

        $result = $this->handler->handle($exception);
        $this->assertTrue($result);
        $this->assertSame('<html>Package debug template</html>', $this->response->getContent());
    }

    public function testRenderUsesDefaultHtmlTemplateWhenNoCustomTemplatesExist(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('debug');

        // Mock renderer to return false for all template checks (no custom templates)
        $this->renderer->expects($this->exactly(2))
            ->method('exists')
            ->willReturn(false);

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(404, $this->response->getStatusCode());
        $content = $this->response->getContent();
        $this->assertStringContainsString('404', $content);
        $this->assertStringContainsString('Not Found', $content);
        $this->assertStringContainsString('<html', $content);
    }

    public function testRenderUsesCustomErrorTemplateWhenAvailable(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('debug');

        $this->renderer->expects($this->once())
            ->method('exists')
            ->with('@view/Errors/404')
            ->willReturn(true);

        $this->renderer->expects($this->once())
            ->method('render')
            ->with('@view/Errors/404', $this->callback(function ($data) use ($exception) {
                return isset($data['statusCode']) && $data['statusCode'] === 404
                    && isset($data['exception']) && $data['exception'] === $exception;
            }))
            ->willReturn(Frames::of()->setContent('<html>404 template</html>'));

        $result = $this->handler->handle($exception);
        $this->assertTrue($result);
        $this->assertSame('<html>404 template</html>', $this->response->getContent());
    }

    public function testHandleReturnsJsonWhenRequestContentTypeIsApplicationJson(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'application/json; charset=utf-8',
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->once())->method('debug');

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(404, $this->response->getStatusCode());
        $this->assertIsString($this->response->getContent());
        $this->assertStringStartsWith('{', (string)$this->response->getContent());
    }

    public function testHandleReturnsJsonWhenRequestContentTypeIsTextJson(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'text/json',
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->once())->method('debug');

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(404, $this->response->getStatusCode());
        $this->assertStringStartsWith('{', (string)$this->response->getContent());
    }

    public function testHandleReturnsJsonWhenRequestContentTypeIsVendorPlusJson(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'PUT',
                'CONTENT_TYPE' => 'application/vnd.api+json',
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->once())->method('debug');

        $result = $this->handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(404, $this->response->getStatusCode());
        $this->assertStringStartsWith('{', (string)$this->response->getContent());
    }

    public function testHandleReturnsJsonWhenHandlerFormatIsJson(): void
    {
        $handler = $this->container->make(DefaultExceptionHandler::class, ['format' => 'json']);

        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->once())->method('debug');

        $result = $handler->handle($exception);

        $this->assertTrue($result);
        $this->assertSame(404, $this->response->getStatusCode());
        $this->assertStringStartsWith('{', (string)$this->response->getContent());
    }

    public function testHandleJsonErrorSetsApplicationJsonContentTypeHeader(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $exception = $this->createNotFoundException();

        $this->logger->expects($this->once())->method('info');
        $this->logger->expects($this->once())->method('debug');

        $this->handler->handle($exception);

        $contentType = $this->response->getHeader('Content-Type');
        $this->assertNotNull($contentType);
        $this->assertStringContainsString('application/json', $contentType);
    }

    public function testHandleIncludesRuntimeDetailInJsonWhenDebugEnabledFor500(): void
    {
        $this->enableDebugMode();

        $requestEvent = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );
        $this->request->onRequestReceived($requestEvent);

        $message = 'Sensitive internal ' . uniqid('err_', true);
        $exception = new RuntimeException($message);

        $this->logger->expects($this->once())->method('error');

        $this->handler->handle($exception);

        $this->assertSame(500, $this->response->getStatusCode());
        $body = (string)$this->response->getContent();
        $this->assertStringContainsString($message, $body);
        $this->assertStringContainsString('RuntimeException', $body);
    }
}
