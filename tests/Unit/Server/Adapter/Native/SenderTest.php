<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server\Adapter\Native;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Http\Event\BodySending;
use Switon\Http\Event\BodySent;
use Switon\Http\Event\HeadersSending;
use Switon\Http\Event\HeadersSent;
use Switon\Http\Exception\HeadersAlreadySentException;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\Server\Adapter\Native\HeadersInterface;
use Switon\Http\Server\Adapter\Native\Sender;
use Switon\Http\Server\Adapter\Native\SenderInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SenderTest extends TestCase
{
    #[Autowired] protected SenderInterface $sender;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected RouterInterface $router;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;

    protected function beforeSetUpHttpContainer(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->container->remove(\Psr\EventDispatcher\EventDispatcherInterface::class);
        $this->container->replace(\Psr\EventDispatcher\EventDispatcherInterface::class, $this->eventDispatcher);
        // Also remove Switon-specific interface mapping if it exists
        $this->container->remove(\Switon\Eventing\EventDispatcherInterface::class);

        // Set up HeadersInterface mock BEFORE property autowiring to prevent real headers_sent() calls
        // This ensures Sender (injected in parent::setUp()) gets the mock instead of real Headers
        $headers = $this->createMock(HeadersInterface::class);
        $headers->method('headersSent')->willReturn(false);
        $headers->method('header')->willReturn(true);
        $headers->method('setcookie')->willReturn(true);
        $this->container->remove(HeadersInterface::class);
        $this->container->replace(HeadersInterface::class, $headers);

        $this->router = $this->createMock(RouterInterface::class);
        $this->container->replace(RouterInterface::class, $this->router);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));

        // HeadersInterface is already set in beforeSetUpHttpContainer()

        // Property autowiring is automatically performed by parent::setUp()
    }

    public function testSendHeadersDispatchesHeadersSendingEvent(): void
    {
        $this->response->setStatus(200);
        $this->response->setHeader('Content-Type', 'text/html');

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->logicalOr(
                $this->isInstanceOf(HeadersSending::class),
                $this->isInstanceOf(HeadersSent::class)
            ));

        $this->router->expects($this->once())
            ->method('getPrefix')
            ->willReturn('');

        $this->sender->sendHeaders();
    }

    public function testSendHeadersSetsCorrectHttpStatusHeader(): void
    {
        $this->response->setStatus(404);
        $this->response->setHeader('Content-Type', 'application/json');
        $this->response->setHeader('X-Custom-Header', 'custom-value');

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch');

        $this->router->expects($this->once())
            ->method('getPrefix')
            ->willReturn('');

        $this->sender->sendHeaders();
    }

    public function testSendBodyOutputsResponseContent(): void
    {
        $this->response->setContent('Test response content');
        $this->response->setStatus(200);

        $this->expectOutputString('Test response content');

        $this->sender->sendBody();
    }

    public function testSendHeadersHandlesCookiesWithRouterPrefix(): void
    {
        $this->response->setStatus(200);
        $this->response->setCookie('test', 'value', 3600, '/path', 'example.com', false, true);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch');

        $this->router->expects($this->once())
            ->method('getPrefix')
            ->willReturn('/api');

        $this->sender->sendHeaders();
    }

    public function testSendHeadersThrowsWhenHeadersAlreadySent(): void
    {
        $headers = $this->createMock(HeadersInterface::class);
        $headers->method('headersSent')->willReturnCallback(function (?string &$file = null, ?int &$line = null): bool {
            $file = '/fake/path.php';
            $line = 99;

            return true;
        });

        $sender = $this->makeSender(['headers' => $headers]);
        $this->expectException(HeadersAlreadySentException::class);
        $sender->sendHeaders();
    }

    public function testSendHeadersEmitsHeaderLineWithoutColonWhenHeaderValueIsNull(): void
    {
        $ctx = $this->response->getContext();
        $ctx->headers['X-Nullish'] = null;

        $headers = $this->createMock(HeadersInterface::class);
        $headers->method('headersSent')->willReturn(false);
        $headers->expects($this->exactly(2))
            ->method('header')
            ->with($this->logicalOr(
                $this->stringStartsWith('HTTP/1.1 '),
                $this->identicalTo('X-Nullish')
            ))
            ->willReturn(true);
        $headers->method('setcookie')->willReturn(true);

        $sender = $this->makeSender(['headers' => $headers]);
        $this->response->setStatus(200);
        $this->router->expects($this->once())->method('getPrefix')->willReturn('');
        $this->eventDispatcher->expects($this->exactly(2))->method('dispatch');
        $sender->sendHeaders();
    }

    public function testSendHeadersPassesEmptyCookiePathWithoutRouterPrefix(): void
    {
        $paths = [];
        $headers = $this->createMock(HeadersInterface::class);
        $headers->method('headersSent')->willReturn(false);
        $headers->method('header')->willReturn(true);
        $headers->method('setcookie')->willReturnCallback(
            function (string $name, mixed $value, int $expire, string $path, ...$rest) use (&$paths): bool {
                $paths[] = $path;

                return true;
            }
        );

        $sender = $this->makeSender(['headers' => $headers]);
        $this->response->setStatus(200);
        $this->response->setCookie('c', 'v', 0, '', null, false, true);
        $this->router->expects($this->once())->method('getPrefix')->willReturn('/api');
        $this->eventDispatcher->expects($this->exactly(2))->method('dispatch');
        $sender->sendHeaders();
        $this->assertSame([''], $paths);
    }

    public function testSendBodyIsNoopFor304Responses(): void
    {
        $this->response->setStatus(304);
        $this->response->setContent('ignored');

        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->expectOutputString('');
        $this->sender->sendBody();
    }

    public function testSendBodyForHeadSetsContentLengthWithoutEchoingBody(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('isVerb')->with('HEAD')->willReturn(true);

        $headers = $this->createMock(HeadersInterface::class);
        $headers->expects($this->once())
            ->method('header')
            ->with('Content-Length: 5');

        $sender = $this->makeSender(['request' => $request, 'headers' => $headers]);
        $this->response->setContent('abcde');
        $this->response->setStatus(200);
        $this->expectOutputString('');
        $sender->sendBody();
    }

    public function testSendBodyOutputsFileWhenResponseHasFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'swt_sender_');
        $this->assertNotFalse($tmp);
        try {
            $this->assertSame(3, file_put_contents($tmp, 'abc'));
            $this->response->getContext()->file = $tmp;
            $this->response->setStatus(200);

            $this->eventDispatcher->expects($this->never())->method('dispatch');

            $this->expectOutputString('abc');
            $this->sender->sendBody();
        } finally {
            @unlink($tmp);
        }
    }

    public function testSendBodyEchoesContentModifiedByBodySendingListener(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $bodySent = null;
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$bodySent): void {
                if ($event instanceof BodySending) {
                    $event->content = 'after';
                }
                if ($event instanceof BodySent) {
                    $bodySent = $event;
                }
            });

        $sender = $this->makeSender(['eventDispatcher' => $dispatcher]);
        $this->response->setContent('before');
        $this->response->setStatus(200);
        $this->expectOutputString('after');
        $sender->sendBody();

        $this->assertInstanceOf(BodySent::class, $bodySent);
        $this->assertSame('after', $bodySent->content);
        $this->assertTrue($bodySent->result);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function makeSender(array $parameters = []): Sender
    {
        return $this->container->make(Sender::class, $parameters);
    }
}
