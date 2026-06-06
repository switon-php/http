<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionMethod;
use Switon\Http\Attribute\ETag;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ETagTest extends TestCase
{
    private RequestInterface $request;
    private ResponseInterface $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = $this->createMock(RequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
    }

    public function testETagWithFieldValue(): void
    {
        // Prepare response content
        $content = json_encode(['id' => 1, 'updated_at' => '2024-01-19']);

        $this->response->method('getContent')->willReturn($content);
        $this->request->method('header')->with('if-none-match')->willReturn(null);

        // Expect ETag header
        $this->response->expects($this->once())
            ->method('setHeader')
            ->with('ETag', '"2024-01-19"');

        // Create ETag interceptor
        $etag = $this->make(ETag::class, [
            'field' => 'updated_at',
            'request' => $this->request,
            'response' => $this->response,
        ]);

        // Execute
        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $etag->postHandle($method, $return);
    }

    public function testETagWithoutField(): void
    {
        // Prepare response content
        $content = json_encode(['data' => 'test']);
        $expectedEtag = md5($content);

        $this->response->method('getContent')->willReturn($content);
        $this->request->method('header')->with('if-none-match')->willReturn(null);

        // Expect ETag header
        $this->response->expects($this->once())
            ->method('setHeader')
            ->with('ETag', "\"{$expectedEtag}\"");

        // Create ETag interceptor (no field)
        $etag = $this->make(ETag::class, [
            'request' => $this->request,
            'response' => $this->response,
        ]);

        // Execute
        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $etag->postHandle($method, $return);
    }

    public function testETagWith304Response(): void
    {
        // Prepare response content
        $content = json_encode(['id' => 1, 'updated_at' => '2024-01-19']);

        $this->response->method('getContent')->willReturn($content);

        // Request header contains matching ETag
        $this->request->method('header')
            ->with('if-none-match')
            ->willReturn('"2024-01-19"');

        // Expect 304 status
        $this->response->expects($this->once())
            ->method('setStatus')
            ->with(304, 'Not Modified');

        // Expect empty content
        $this->response->expects($this->once())
            ->method('setContent')
            ->with('');

        // Create ETag interceptor
        $etag = $this->make(ETag::class, [
            'field' => 'updated_at',
            'request' => $this->request,
            'response' => $this->response,
        ]);

        // Execute
        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $etag->postHandle($method, $return);
    }

    public function testETagWithEmptyContent(): void
    {
        // Empty response content
        $this->response->method('getContent')->willReturn('');

        // Should not set any headers
        $this->response->expects($this->never())->method('setHeader');

        // Create ETag interceptor
        $etag = $this->make(ETag::class, [
            'request' => $this->request,
            'response' => $this->response,
        ]);

        // Execute
        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $etag->postHandle($method, $return);
    }

    public function testETagFieldNotFoundFallbackToHash(): void
    {
        // Response content does not include the specified field
        $content = json_encode(['id' => 1, 'name' => 'John']);
        $expectedEtag = md5($content);

        $this->response->method('getContent')->willReturn($content);
        $this->request->method('header')->with('if-none-match')->willReturn(null);

        // Expect MD5 ETag
        $this->response->expects($this->once())
            ->method('setHeader')
            ->with('ETag', "\"{$expectedEtag}\"");

        // Create ETag interceptor (field not present)
        $etag = $this->make(ETag::class, [
            'field' => 'updated_at',
            'request' => $this->request,
            'response' => $this->response,
        ]);

        // Execute
        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $etag->postHandle($method, $return);
    }

    public function testETagWithNonJsonContent(): void
    {
        // Non-JSON response content
        $content = 'plain text content';
        $expectedEtag = md5($content);

        $this->response->method('getContent')->willReturn($content);
        $this->request->method('header')->with('if-none-match')->willReturn(null);

        // Expect MD5 ETag
        $this->response->expects($this->once())
            ->method('setHeader')
            ->with('ETag', "\"{$expectedEtag}\"");

        // Create ETag interceptor
        $etag = $this->make(ETag::class, [
            'field' => 'field',
            'request' => $this->request,
            'response' => $this->response,
        ]);

        // Execute
        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $etag->postHandle($method, $return);
    }

    public function testPreHandleAlwaysReturnsTrue(): void
    {
        $etag = new ETag();
        $method = new ReflectionMethod(self::class, 'setUp');

        $this->assertTrue($etag->preHandle($method));
    }

}
