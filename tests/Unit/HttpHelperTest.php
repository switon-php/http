<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Http\Tests\TestCase;
use Switon\Http\UrlGeneratorInterface;

use function action;
use function url;

#[AllowMockObjectsWithoutExpectations]
class HttpHelperTest extends TestCase
{
    protected UrlGeneratorInterface $urlGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->container->replace(UrlGeneratorInterface::class, $this->urlGenerator);
    }

    public function testUrlHelperForwardsToUrlGenerator(): void
    {
        $this->urlGenerator->expects($this->once())
            ->method('url')
            ->with('/login', 'https')
            ->willReturn('https://example.com/login');

        $this->assertSame('https://example.com/login', url('/login', 'https'));
    }

    public function testActionHelperForwardsToUrlGenerator(): void
    {
        $this->urlGenerator->expects($this->once())
            ->method('action')
            ->with('login', false)
            ->willReturn('/api/admin/user/login');

        $this->assertSame('/api/admin/user/login', action('login'));
    }

    public function testUrlHelperForwardsArrayArgumentsToUrlGenerator(): void
    {
        $args = ['/items', 'page' => 2];
        $this->urlGenerator->expects($this->once())
            ->method('url')
            ->with($args, false)
            ->willReturn('/api/items?page=2');

        $this->assertSame('/api/items?page=2', url($args));
    }

    public function testActionHelperPassesSchemeArgumentToUrlGenerator(): void
    {
        $this->urlGenerator->expects($this->once())
            ->method('action')
            ->with('logout', 'https')
            ->willReturn('https://example.com/api/logout');

        $this->assertSame('https://example.com/api/logout', action('logout', 'https'));
    }
}
