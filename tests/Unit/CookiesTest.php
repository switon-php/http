<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Core\Attribute\Autowired;
use Switon\Http\CookiesContext;
use Switon\Http\CookiesInterface;
use Switon\Http\Event\RequestReceived;
use Switon\Http\ResponseInterface;
use Switon\Http\Tests\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CookiesTest extends TestCase
{
    #[Autowired] protected CookiesInterface $cookies;
    #[Autowired] protected ResponseInterface $response;

    protected function beforeSetUpHttpContainer(): void
    {
        // Set up ResponseInterface mock BEFORE property autowiring to prevent container from resolving to real Response
        // This ensures Cookies (injected in parent::setUp()) gets the mock instead of real Response instance
        $this->response = $this->createMock(ResponseInterface::class);
        $this->container->remove(ResponseInterface::class);
        $this->container->replace(ResponseInterface::class, $this->response);
    }

    // setUp() is not needed - beforeSetUpHttpContainer() and property autowiring are handled by parent

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

    public function testGetContextReturnsCookiesContext(): void
    {
        $context = $this->cookies->getContext();

        $this->assertInstanceOf(CookiesContext::class, $context);
    }

    public function testOnRequestReceivedPopulatesCookiesThatCanBeRetrieved(): void
    {
        $event = $this->createRequestEvent(
            cookie: ['name' => 'John', 'session' => 'abc123']
        );

        $this->cookies->onRequestReceived($event);

        $this->assertSame('John', $this->cookies->get('name'));
        $this->assertSame('abc123', $this->cookies->get('session'));
    }

    public function testAllReturnsAllCookies(): void
    {
        $event = $this->createRequestEvent(
            cookie: ['cookie1' => 'value1', 'cookie2' => 'value2']
        );

        $this->cookies->onRequestReceived($event);

        $all = $this->cookies->all();

        $this->assertSame('value1', $all['cookie1']);
        $this->assertSame('value2', $all['cookie2']);
        $this->assertCount(2, $all);
    }

    public function testSetSetsCookieAndCallsResponseSetCookie(): void
    {
        $this->response->expects($this->once())
            ->method('setCookie')
            ->with('test', 'value', 3600, '/path', 'example.com', true, true);

        $result = $this->cookies->set('test', 'value', 3600, '/path', 'example.com', true, true);

        $this->assertSame($this->cookies, $result);
        $this->assertSame('value', $this->cookies->get('test'));
    }

    public function testSetUsesDefaultParameters(): void
    {
        $this->response->expects($this->once())
            ->method('setCookie')
            ->with('test', 'value', 0, '', '', false, true);

        $this->cookies->set('test', 'value');
    }

    public function testGetReturnsCookieValueOrDefault(): void
    {
        $event = $this->createRequestEvent(
            cookie: ['name' => 'John']
        );

        $this->cookies->onRequestReceived($event);

        $this->assertSame('John', $this->cookies->get('name'));
        $this->assertSame(null, $this->cookies->get('nonexistent'));
        $this->assertSame('default', $this->cookies->get('nonexistent', 'default'));
    }

    public function testHasChecksIfCookieExists(): void
    {
        $event = $this->createRequestEvent(
            cookie: ['exists' => 'value']
        );

        $this->cookies->onRequestReceived($event);

        $this->assertTrue($this->cookies->has('exists'));
        $this->assertFalse($this->cookies->has('nonexistent'));
    }

    public function testHasReturnsFalseWhenCookieValueIsNull(): void
    {
        $event = $this->createRequestEvent(
            cookie: ['nullable' => null]
        );

        $this->cookies->onRequestReceived($event);

        $this->assertFalse($this->cookies->has('nullable'));
        $this->assertNull($this->cookies->get('nullable'));
    }

    public function testDeleteRemovesCookieAndCallsResponseSetCookie(): void
    {
        $event = $this->createRequestEvent(
            cookie: ['test' => 'value']
        );

        $this->cookies->onRequestReceived($event);

        $this->assertTrue($this->cookies->has('test'));

        $this->response->expects($this->once())
            ->method('setCookie')
            ->with('test', 'deleted', -1, '/', '');

        $result = $this->cookies->delete('test');

        $this->assertSame($this->cookies, $result);
        $this->assertFalse($this->cookies->has('test'));
    }

    public function testDeleteUsesCustomPathAndDomain(): void
    {
        $event = $this->createRequestEvent(
            cookie: ['test' => 'value']
        );

        $this->cookies->onRequestReceived($event);

        $this->response->expects($this->once())
            ->method('setCookie')
            ->with('test', 'deleted', -1, '/custom', 'example.com');

        $this->cookies->delete('test', '/custom', 'example.com');
    }

    public function testJsonSerializeReturnsAllCookies(): void
    {
        $event = $this->createRequestEvent(
            cookie: ['cookie1' => 'value1', 'cookie2' => 'value2']
        );

        $this->cookies->onRequestReceived($event);

        $json = $this->cookies->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertSame('value1', $json['cookie1']);
        $this->assertSame('value2', $json['cookie2']);
    }

    public function testJsonSerializeIncludesCookiesSetAfterRequest(): void
    {
        $event = $this->createRequestEvent(cookie: ['a' => '1']);
        $this->cookies->onRequestReceived($event);

        $this->response->expects($this->once())
            ->method('setCookie')
            ->with('b', '2', 0, '', '', false, true);

        $this->cookies->set('b', '2');

        $json = $this->cookies->jsonSerialize();
        $this->assertSame('1', $json['a']);
        $this->assertSame('2', $json['b']);
    }
}
