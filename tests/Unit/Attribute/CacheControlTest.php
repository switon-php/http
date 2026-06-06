<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Attribute;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionMethod;
use Switon\Http\Attribute\CacheControl;
use Switon\Http\ResponseInterface;
use Switon\Http\Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CacheControlTest extends TestCase
{
    private ResponseInterface $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->response = $this->createMock(ResponseInterface::class);
    }

    public function testSimpleMaxAge(): void
    {
        $this->response->expects($this->once())
            ->method('setHeader')
            ->with('Cache-Control', 'public, max-age=3600');

        $cacheControl = $this->make(CacheControl::class, [
            'maxAge' => 3600,
            'response' => $this->response,
        ]);

        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $cacheControl->postHandle($method, $return);
    }

    public function testPrivateCache(): void
    {
        $this->response->expects($this->once())
            ->method('setHeader')
            ->with('Cache-Control', 'private, max-age=1800');

        $cacheControl = $this->make(CacheControl::class, [
            'maxAge' => 1800,
            'public' => false,
            'response' => $this->response,
        ]);

        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $cacheControl->postHandle($method, $return);
    }

    public function testWithSMaxAge(): void
    {
        $this->response->expects($this->once())
            ->method('setHeader')
            ->with('Cache-Control', 'public, max-age=3600, s-maxage=7200');

        $cacheControl = $this->make(CacheControl::class, [
            'maxAge' => 3600,
            'sMaxAge' => 7200,
            'response' => $this->response,
        ]);

        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $cacheControl->postHandle($method, $return);
    }

    public function testWithMustRevalidate(): void
    {
        $this->response->expects($this->once())
            ->method('setHeader')
            ->with('Cache-Control', 'public, max-age=3600, must-revalidate');

        $cacheControl = $this->make(CacheControl::class, [
            'maxAge' => 3600,
            'mustRevalidate' => true,
            'response' => $this->response,
        ]);

        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $cacheControl->postHandle($method, $return);
    }

    public function testNoCache(): void
    {
        $this->response->expects($this->once())
            ->method('setHeader')
            ->with('Cache-Control', 'no-cache');

        $cacheControl = $this->make(CacheControl::class, [
            'noCache' => true,
            'response' => $this->response,
        ]);

        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $cacheControl->postHandle($method, $return);
    }

    public function testNoStore(): void
    {
        $this->response->expects($this->once())
            ->method('setHeader')
            ->with('Cache-Control', 'no-store, no-cache');

        $cacheControl = $this->make(CacheControl::class, [
            'noStore' => true,
            'response' => $this->response,
        ]);

        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $cacheControl->postHandle($method, $return);
    }

    public function testNoStoreOverridesOtherSettings(): void
    {
        // no-store should override other settings
        $this->response->expects($this->once())
            ->method('setHeader')
            ->with('Cache-Control', 'no-store, no-cache');

        $cacheControl = $this->make(CacheControl::class, [
            'maxAge' => 3600,
            'public' => true,
            'noStore' => true,
            'response' => $this->response,
        ]);

        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $cacheControl->postHandle($method, $return);
    }

    public function testComplexConfiguration(): void
    {
        $this->response->expects($this->once())
            ->method('setHeader')
            ->with('Cache-Control', 'private, max-age=1800, s-maxage=3600, must-revalidate');

        $cacheControl = $this->make(CacheControl::class, [
            'maxAge' => 1800,
            'public' => false,
            'sMaxAge' => 3600,
            'mustRevalidate' => true,
            'response' => $this->response,
        ]);

        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $cacheControl->postHandle($method, $return);
    }

    public function testOnlyPublicWithoutMaxAge(): void
    {
        $this->response->expects($this->once())
            ->method('setHeader')
            ->with('Cache-Control', 'public');

        $cacheControl = $this->make(CacheControl::class, [
            'response' => $this->response,
        ]);

        $return = null;
        $method = new ReflectionMethod(self::class, 'setUp');
        $cacheControl->postHandle($method, $return);
    }

    public function testPreHandleAlwaysReturnsTrue(): void
    {
        $cacheControl = new CacheControl();
        $method = new ReflectionMethod(self::class, 'setUp');

        $this->assertTrue($cacheControl->preHandle($method));
    }

}
