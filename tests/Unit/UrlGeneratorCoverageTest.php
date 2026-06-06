<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Http\RequestInterface;
use Switon\Http\UrlGenerator;
use Switon\Routing\RouterInterface;

final class UrlGeneratorCoverageTest extends TestCase
{
    public function testUrlUsesArrayPathAndAppliesPrefix(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('getPrefix')->willReturn('/api');

        $request = $this->createStub(RequestInterface::class);

        $generator = new TestableUrlGenerator();
        $generator->setDependencies($router, $request);

        $this->assertSame('/api/users?page=2', $generator->url(['/users', 'page' => 2]));
    }

    public function testActionUsesArrayPathAndResolvesRelativeSegment(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('getPrefix')->willReturn('/api');

        $request = $this->createStub(RequestInterface::class);
        $request->method('path')->willReturn('/api/admin/users/list');

        $generator = new TestableUrlGenerator();
        $generator->setDependencies($router, $request);

        $this->assertSame('/api/admin/users/login?tab=sms', $generator->action(['login', 'tab' => 'sms']));
    }

    public function testCurrentAppPathReturnsRequestPathWhenPrefixDoesNotMatch(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('getPrefix')->willReturn('/api');

        $request = $this->createStub(RequestInterface::class);
        $request->method('path')->willReturn('/plain/path');

        $generator = new TestableUrlGenerator();
        $generator->setDependencies($router, $request);

        $this->assertSame('/plain/path', $generator->currentAppPathForTest());
    }

    public function testCurrentAppPathReturnsRootWhenRequestPathIsEmpty(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('getPrefix')->willReturn('/api');

        $request = $this->createStub(RequestInterface::class);
        $request->method('path')->willReturn('');

        $generator = new TestableUrlGenerator();
        $generator->setDependencies($router, $request);

        $this->assertSame('/', $generator->currentAppPathForTest());
    }

    public function testParentPathHandlesRootAndNestedPaths(): void
    {
        $generator = new TestableUrlGenerator();

        $this->assertSame('/', $generator->parentPathForTest(''));
        $this->assertSame('/', $generator->parentPathForTest('/'));
        $this->assertSame('/admin', $generator->parentPathForTest('/admin/users'));
    }

    public function testNormalizeAbsolutePathDropsDotSegments(): void
    {
        $generator = new TestableUrlGenerator();

        $this->assertSame('/admin/login', $generator->normalizeAbsolutePathForTest('/admin/users/.././login'));
    }

    public function testNormalizeAbsolutePathReturnsRootWhenSegmentsCancelCompletely(): void
    {
        $generator = new TestableUrlGenerator();

        $this->assertSame('/', $generator->normalizeAbsolutePathForTest('/a/b/../../../'));
    }

    public function testNormalizeAbsolutePathSkipsEmptySegmentsFromRepeatedSlashes(): void
    {
        $generator = new TestableUrlGenerator();

        $this->assertSame('/a/c', $generator->normalizeAbsolutePathForTest('/a//b/../c'));
    }
}

final class TestableUrlGenerator extends UrlGenerator
{
    public function setDependencies(RouterInterface $router, RequestInterface $request): void
    {
        $this->router = $router;
        $this->request = $request;
    }

    public function currentAppPathForTest(): string
    {
        return $this->currentAppPath();
    }

    public function parentPathForTest(string $path): string
    {
        return $this->parentPath($path);
    }

    public function normalizeAbsolutePathForTest(string $path): string
    {
        return $this->normalizeAbsolutePath($path);
    }
}
