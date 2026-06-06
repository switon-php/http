<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Http\RequestInterface;
use Switon\Http\Response\JsonRendererInterface;
use Switon\Http\ResponseContext;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Http\UrlGeneratorInterface;
use Stringable;

use function sys_get_temp_dir;
use function time;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ResponseTest extends TestCase
{
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected UrlGeneratorInterface $urlGenerator;
    #[Autowired] protected ServerInterface $server;
    #[Autowired] protected JsonRendererInterface $jsonRenderer;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->container->replace(UrlGeneratorInterface::class, $this->urlGenerator);

        $this->server = $this->createMock(ServerInterface::class);
        $this->container->replace(ServerInterface::class, $this->server);

        $this->jsonRenderer = $this->createMock(JsonRendererInterface::class);
        $this->container->replace(JsonRendererInterface::class, $this->jsonRenderer);

        // Property autowiring is automatically performed by parent::setUp()
    }

    /**
     * Create a fresh Response instance with mocks already injected.
     *
     * Use this when you need to configure mocks with specific expectations
     * BEFORE the Response instance is created.
     */
    protected function createResponse(): ResponseInterface
    {
        $this->container->remove(ResponseInterface::class);
        return $this->container->get(ResponseInterface::class);
    }

    public function testGetContextReturnsResponseContext(): void
    {
        $context = $this->response->getContext();

        $this->assertInstanceOf(ResponseContext::class, $context);
    }

    public function testSetCookieSetsCookieThatCanBeRetrieved(): void
    {
        $this->response->setCookie('test', 'value', 3600, '/', 'example.com', true, true);

        $cookies = $this->response->getCookies();

        $this->assertArrayHasKey('test', $cookies);
        $this->assertSame('test', $cookies['test']['name']);
        $this->assertSame('value', $cookies['test']['value']);
        $this->assertGreaterThan(time(), $cookies['test']['expire']);
        $this->assertSame('/', $cookies['test']['path']);
        $this->assertSame('example.com', $cookies['test']['domain']);
        $this->assertTrue($cookies['test']['secure']);
        $this->assertTrue($cookies['test']['httponly']);
    }

    public function testSetCookieAddsCurrentTimeToExpire(): void
    {
        $expire = 3600;
        $this->response->setCookie('test', 'value', $expire);

        $cookies = $this->response->getCookies();

        $this->assertGreaterThan(time(), $cookies['test']['expire']);
        $this->assertLessThanOrEqual(time() + $expire + 1, $cookies['test']['expire']);
    }

    public function testSetStatusSetsStatusCodeAndText(): void
    {
        $this->response->setStatus(404, 'Not Found');

        $this->assertSame(404, $this->response->getStatusCode());
        $this->assertSame('404 Not Found', $this->response->getStatus());
        $this->assertSame('Not Found', $this->response->getStatusText());
    }

    public function testSetStatusUsesDefaultStatusText(): void
    {
        $this->response->setStatus(200);

        $this->assertSame(200, $this->response->getStatusCode());
        $this->assertSame('OK', $this->response->getStatusText());
    }

    public function testSetStatusWithCustomTextUsesCustomText(): void
    {
        $this->response->setStatus(200, 'Custom OK');

        $this->assertSame('200 Custom OK', $this->response->getStatus());
        $this->assertSame('Custom OK', $this->response->getStatusText());
    }

    public function testSetStatusWithoutTextUsesDefaultText(): void
    {
        $this->response->setStatus(404);

        $this->assertSame('404 Not Found', $this->response->getStatus());
        $this->assertSame('Not Found', $this->response->getStatusText());
    }

    public function testGetStatusTextReturnsTextForKnownStatusCodes(): void
    {
        $this->assertSame('OK', $this->response->getStatusText(200));
        $this->assertSame('Created', $this->response->getStatusText(201));
        $this->assertSame('Accepted', $this->response->getStatusText(202));
        $this->assertSame('No Content', $this->response->getStatusText(204));
        $this->assertSame('Partial Content', $this->response->getStatusText(206));

        $this->assertSame('Moved Permanently', $this->response->getStatusText(301));
        $this->assertSame('Found', $this->response->getStatusText(302));
        $this->assertSame('Not Modified', $this->response->getStatusText(304));
        $this->assertSame('Temporary Redirect', $this->response->getStatusText(307));
        $this->assertSame('Permanent Redirect', $this->response->getStatusText(308));

        $this->assertSame('Bad Request', $this->response->getStatusText(400));
        $this->assertSame('Unauthorized', $this->response->getStatusText(401));
        $this->assertSame('Payment Required', $this->response->getStatusText(402));
        $this->assertSame('Forbidden', $this->response->getStatusText(403));
        $this->assertSame('Not Found', $this->response->getStatusText(404));
        $this->assertSame('Method Not Allowed', $this->response->getStatusText(405));
        $this->assertSame('Request Time-out', $this->response->getStatusText(408));
        $this->assertSame('Conflict', $this->response->getStatusText(409));
        $this->assertSame('Gone', $this->response->getStatusText(410));
        $this->assertSame('Unprocessable entity', $this->response->getStatusText(422));
        $this->assertSame('Too Many Requests', $this->response->getStatusText(429));

        $this->assertSame('Internal Server Error', $this->response->getStatusText(500));
        $this->assertSame('Not Implemented', $this->response->getStatusText(501));
        $this->assertSame('Bad Gateway or Proxy Error', $this->response->getStatusText(502));
        $this->assertSame('Service Unavailable', $this->response->getStatusText(503));
        $this->assertSame('Gateway Time-out', $this->response->getStatusText(504));

        $this->assertSame('App Error', $this->response->getStatusText(999));

        $this->response->setStatus(404, 'Custom Not Found');
        $this->assertSame('Custom Not Found', $this->response->getStatusText());
    }

    public function testRedirectThrowsStopFlowAndSetsRedirectHeaders(): void
    {
        // Arrange
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('/login')
            ->willReturn('/login');
        $response = $this->createResponse();

        // Act & Assert
        $this->expectException(\Switon\Core\StopFlow::class);

        try {
            $response->redirect('/login');
        } catch (\Switon\Core\StopFlow $e) {
            $this->assertEquals(302, $response->getStatusCode());
            $this->assertEquals('/login', $response->getHeader('Location'));
            throw $e;
        }
    }

    public function testRedirectWithPermanentlyFlagSets301Status(): void
    {
        // Arrange
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('/permanent')
            ->willReturn('/permanent');
        $response = $this->createResponse();

        // Act & Assert
        $this->expectException(\Switon\Core\StopFlow::class);

        try {
            $response->redirect('/permanent', false);
        } catch (\Switon\Core\StopFlow $e) {
            $this->assertEquals(301, $response->getStatusCode());
            $this->assertEquals('/permanent', $response->getHeader('Location'));
            throw $e;
        }
    }

    public function testSetHeaderSetsHeaderThatCanBeRetrieved(): void
    {
        $this->response->setHeader('Content-Type', 'application/json');

        $this->assertSame('application/json', $this->response->getHeader('Content-Type'));
        $this->assertTrue($this->response->hasHeader('Content-Type'));
    }

    public function testGetHeaderReturnsHeaderValueOrDefault(): void
    {
        $this->response->setHeader('X-Custom', 'value');

        $this->assertSame('value', $this->response->getHeader('X-Custom'));
        $this->assertSame(null, $this->response->getHeader('Nonexistent'));
        $this->assertSame('default', $this->response->getHeader('Nonexistent', 'default'));
    }

    public function testHasHeaderChecksIfHeaderExists(): void
    {
        $this->assertFalse($this->response->hasHeader('X-Custom'));

        $this->response->setHeader('X-Custom', 'value');

        $this->assertTrue($this->response->hasHeader('X-Custom'));
    }

    public function testSetContentSetsContentThatCanBeRetrieved(): void
    {
        $content = 'Hello World';
        $this->response->setContent($content);

        $this->assertSame($content, $this->response->getContent());
    }

    public function testJsonSetsStatusAndRendersJsonContent(): void
    {
        // Arrange
        $data = ['key' => 'value'];
        $status = 201;

        $this->jsonRenderer->expects($this->once())
            ->method('render')
            ->with($data, 0);
        $response = $this->createResponse();

        // Act
        $result = $response->json($data, $status);

        // Assert
        $this->assertSame($response, $result);
        $this->assertSame($status, $response->getStatusCode());
    }

    public function testRawSetsStatusContentTypeAndContent(): void
    {
        $content = 'raw content';
        $contentType = 'text/xml';
        $status = 200;

        $result = $this->response->raw($content, $contentType, $status);

        $this->assertSame($this->response, $result);
        $this->assertSame($status, $this->response->getStatusCode());
        $this->assertSame($contentType, $this->response->getHeader('Content-Type'));
        $this->assertSame($content, $this->response->getContent());
    }

    public function testTextSetsStatusContentTypeWithCharsetAndContent(): void
    {
        $content = 'Hello World';
        $contentType = 'text/plain';
        $status = 200;

        $result = $this->response->text($content, $contentType, $status);

        $this->assertSame($this->response, $result);
        $this->assertSame($status, $this->response->getStatusCode());
        $this->assertSame('text/plain; charset=utf-8', $this->response->getHeader('Content-Type'));
        $this->assertSame($content, $this->response->getContent());
    }

    public function testTextUsesDefaultContentType(): void
    {
        $content = 'Hello World';

        $this->response->text($content);

        $this->assertSame('text/plain; charset=utf-8', $this->response->getHeader('Content-Type'));
    }

    public function testSetCookieWithExpireZeroDoesNotAddCurrentTime(): void
    {
        $this->response->setCookie('test', 'value', 0);

        $cookies = $this->response->getCookies();

        $this->assertSame(0, $cookies['test']['expire']);
    }

    public function testSetCookieWithExpireLessThanCurrentTimeAddsCurrentTime(): void
    {
        $pastTime = time() - 3600;

        $this->response->setCookie('test', 'value', $pastTime);

        $cookies = $this->response->getCookies();

        $this->assertGreaterThan($pastTime, $cookies['test']['expire']);
    }

    public function testSetCookieWithExpireGreaterThanCurrentTimeDoesNotAddCurrentTime(): void
    {
        $futureTime = time() + 3600;

        $this->response->setCookie('test', 'value', $futureTime);

        $cookies = $this->response->getCookies();

        $this->assertSame($futureTime, $cookies['test']['expire']);
    }

    public function testSetCookieWithNullPathUsesNull(): void
    {
        $this->response->setCookie('test', 'value', 3600, null);

        $cookies = $this->response->getCookies();

        $this->assertSame(null, $cookies['test']['path']);
    }

    public function testSetCookieWithNullDomainUsesNull(): void
    {
        $this->response->setCookie('test', 'value', 3600, '/', null);

        $cookies = $this->response->getCookies();

        $this->assertSame(null, $cookies['test']['domain']);
    }

    public function testGetCookiesReturnsAllCookies(): void
    {
        $this->response->setCookie('cookie1', 'value1');
        $this->response->setCookie('cookie2', 'value2');

        $cookies = $this->response->getCookies();

        $this->assertArrayHasKey('cookie1', $cookies);
        $this->assertArrayHasKey('cookie2', $cookies);
        $this->assertCount(2, $cookies);
    }

    public function testGetStatusCodeReturnsStatusCode(): void
    {
        $this->response->setStatus(201);

        $this->assertSame(201, $this->response->getStatusCode());
    }

    public function testGetStatusReturnsStatusCodeAndText(): void
    {
        $this->response->setStatus(404, 'Not Found');

        $this->assertSame('404 Not Found', $this->response->getStatus());
    }

    public function testHasContentChecksIfContentExists(): void
    {
        $this->assertFalse($this->response->hasContent());

        $this->response->setContent('Hello');
        $this->assertTrue($this->response->hasContent());

        $this->response->setContent('');
        $this->assertFalse($this->response->hasContent());

        $this->response->setContent(null);
        $this->assertFalse($this->response->hasContent());
    }

    public function testSetFileSetsFilePathThatCanBeRetrieved(): void
    {
        $pathAlias = $this->container->get(\Switon\Core\PathAlias::class);
        $pathAlias->set('@app', sys_get_temp_dir() . '/test_app');

        $this->response->setFile('@app/View/test.sword');

        $this->assertTrue($this->response->hasFile());
        $this->assertNotSame(null, $this->response->getFile());
    }

    public function testGetFileReturnsFilePath(): void
    {
        $this->assertSame(null, $this->response->getFile());

        $pathAlias = $this->container->get(\Switon\Core\PathAlias::class);
        $pathAlias->set('@app', sys_get_temp_dir() . '/test_app');

        $this->response->setFile('@app/View/test.sword');

        $file = $this->response->getFile();
        $this->assertNotSame(null, $file);
        $this->assertIsString($file);
    }

    public function testHasFileChecksIfFileIsSet(): void
    {
        $this->assertFalse($this->response->hasFile());

        $pathAlias = $this->container->get(\Switon\Core\PathAlias::class);
        $pathAlias->set('@app', sys_get_temp_dir() . '/test_app');

        $this->response->setFile('@app/View/test.sword');

        $this->assertTrue($this->response->hasFile());
    }

    public function testGetHeadersReturnsAllHeaders(): void
    {
        $this->response->setHeader('Header1', 'Value1');
        $this->response->setHeader('Header2', 'Value2');

        $headers = $this->response->getHeaders();

        $this->assertArrayHasKey('Header1', $headers);
        $this->assertArrayHasKey('Header2', $headers);
        $this->assertSame('Value1', $headers['Header1']);
        $this->assertSame('Value2', $headers['Header2']);
    }

    public function testIsChunkedReturnsChunkedStatus(): void
    {
        $this->assertFalse($this->response->isChunked());

        $this->server->expects($this->once())
            ->method('sendHeaders');
        $this->server->expects($this->once())
            ->method('write')
            ->with('chunk')
            ->willReturn(true);

        $this->response->write('chunk');

        $this->assertTrue($this->response->isChunked());
    }

    public function testWriteSetsChunkedEncodingAndCallsServerWrite(): void
    {
        $this->server->expects($this->once())
            ->method('sendHeaders');
        $this->server->expects($this->once())
            ->method('write')
            ->with('test chunk')
            ->willReturn(true);

        $result = $this->response->write('test chunk');

        $this->assertTrue($result);
        $this->assertTrue($this->response->isChunked());
        $this->assertSame('chunked', $this->response->getHeader('Transfer-Encoding'));
    }

    public function testRedirectSetsTemporaryRedirectStatusAndLocationHeader(): void
    {
        // Arrange
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('/test')
            ->willReturn('/test');
        $response = $this->createResponse();

        // Act & Assert
        try {
            $response->redirect('/test', true);
            $this->fail('Expected StopFlow exception');
        } catch (\Switon\Core\StopFlow $e) {
            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame('Temporarily Moved', $response->getStatusText());
            $this->assertSame('/test', $response->getHeader('Location'));
        }
    }

    public function testRedirectSetsPermanentRedirectStatus(): void
    {
        // Arrange
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with('/test')
            ->willReturn('/test');
        $response = $this->createResponse();

        // Act & Assert
        try {
            $response->redirect('/test', false);
            $this->fail('Expected StopFlow exception');
        } catch (\Switon\Core\StopFlow $e) {
            $this->assertSame(301, $response->getStatusCode());
            $this->assertSame('Permanently Moved', $response->getStatusText());
        }
    }

    public function testJsonWithCustomOptionsPassesOptionsToRenderer(): void
    {
        // Arrange
        $data = ['key' => 'value'];
        $options = JSON_PRETTY_PRINT;

        $this->jsonRenderer->expects($this->once())
            ->method('render')
            ->with($data, $options);
        $response = $this->createResponse();

        // Act
        $response->json($data, 200, $options);
    }

    public function testRawWithCustomStatusCodeSetsStatus(): void
    {
        $this->response->raw('content', 'text/plain', 201);

        $this->assertSame(201, $this->response->getStatusCode());
    }

    public function testTextWithCustomContentTypeSetsContentType(): void
    {
        $this->response->text('Hello', 'text/html');

        $this->assertSame('text/html; charset=utf-8', $this->response->getHeader('Content-Type'));
    }

    public function testWriteHandlesStringableObjects(): void
    {
        $stringable = new class () implements Stringable {
            public function __toString(): string
            {
                return 'stringable content';
            }
        };

        $this->server->expects($this->once())
            ->method('sendHeaders');
        $this->server->expects($this->once())
            ->method('write')
            ->with('stringable content')
            ->willReturn(true);

        $this->response->write($stringable);

        $this->assertTrue($this->response->isChunked());
    }

    public function testWriteReturnsFalseWhenServerWriteFails(): void
    {
        $this->server->expects($this->once())
            ->method('sendHeaders');
        $this->server->expects($this->once())
            ->method('write')
            ->willReturn(false);

        $result = $this->response->write('chunk');

        $this->assertFalse($result);
    }
}
