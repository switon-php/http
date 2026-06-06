<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Http\Exception\InvalidUrlPathException;
use Switon\Http\RequestInterface;
use Switon\Http\Tests\TestCase;
use Switon\Http\UrlGeneratorInterface;
use Switon\Routing\RouterInterface;

/**
 * Test cases for UrlGenerator functionality.
 *
 * Tests URL generation: generate(), prefix application, query parameters, fragments, absolute URLs.
 */
#[AllowMockObjectsWithoutExpectations]
class UrlGeneratorTest extends TestCase
{
    protected UrlGeneratorInterface $urlGenerator;
    protected RouterInterface $router;
    protected RequestInterface $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = $this->createMock(RouterInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
    }

    /**
     * Create UrlGenerator with configured mocks.
     * Call this after configuring mock expectations.
     */
    protected function createUrlGenerator(): UrlGeneratorInterface
    {
        $this->container->remove(RouterInterface::class);
        $this->container->set(RouterInterface::class, $this->router);
        $this->container->remove(RequestInterface::class);
        $this->container->set(RequestInterface::class, $this->request);
        $this->container->remove(UrlGeneratorInterface::class);

        return $this->container->get(UrlGeneratorInterface::class);
    }

    /**
     * Test generate with simple string path.
     *
     * Verifies that generate() can generate URLs from string paths.
     */
    public function testGenerateWithStringPath(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('');
        $urlGenerator = $this->createUrlGenerator();
        $path = '/users';

        // Act
        $url = $urlGenerator->generate($path);

        // Assert
        $this->assertSame('/users', $url, 'generate() should return the path as-is when no prefix');
    }

    /**
     * Test generate applies prefix.
     *
     * Verifies that generate() applies router prefix to generated URLs.
     */
    public function testGenerateAppliesPrefix(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('/api/v1');
        $urlGenerator = $this->createUrlGenerator();
        $path = '/users';

        // Act
        $url = $urlGenerator->generate($path);

        // Assert
        $this->assertSame('/api/v1/users', $url, 'generate() should apply prefix to URL');
    }

    /**
     * Test generate with array format.
     *
     * Verifies that generate() can generate URLs from array format.
     */
    public function testGenerateWithArrayFormat(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('');
        $urlGenerator = $this->createUrlGenerator();
        $args = ['/users', 'page' => 2, 'limit' => 20];

        // Act
        $url = $urlGenerator->generate($args);

        // Assert
        $this->assertStringContainsString('/users', $url);
        $this->assertStringContainsString('page=2', $url);
        $this->assertStringContainsString('limit=20', $url);
    }

    /**
     * Test generate with query parameters in string format.
     *
     * Verifies that generate() handles query parameters in string format.
     */
    public function testGenerateWithQueryString(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('');
        $urlGenerator = $this->createUrlGenerator();
        $path = '/users?page=2&limit=20';

        // Act
        $url = $urlGenerator->generate($path);

        // Assert
        $this->assertStringContainsString('/users', $url);
        $this->assertStringContainsString('page=2', $url);
        $this->assertStringContainsString('limit=20', $url);
    }

    /**
     * Test generate with fragment identifier.
     *
     * Verifies that generate() handles fragment identifiers (hash parameter).
     */
    public function testGenerateWithFragment(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('');
        $urlGenerator = $this->createUrlGenerator();
        $args = ['/users', 'page' => 2, '#' => 'section1'];

        // Act
        $url = $urlGenerator->generate($args);

        // Assert
        $this->assertStringContainsString('/users', $url);
        $this->assertStringContainsString('page=2', $url);
        $this->assertStringEndsWith('#section1', $url);
    }

    /**
     * Test generate with absolute URL (scheme = true).
     *
     * Verifies that generate() can generate absolute URLs when scheme is true.
     */
    public function testGenerateWithAbsoluteUrl(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('');
        $this->request->method('scheme')->willReturn('https');
        $this->request->method('header')->with('host')->willReturn('example.com');
        $urlGenerator = $this->createUrlGenerator();
        $path = '/users';

        // Act
        $url = $urlGenerator->generate($path, true);

        // Assert
        $this->assertSame('https://example.com/users', $url, 'generate() should create absolute URL with current scheme');
    }

    /**
     * Test generate with specific scheme.
     *
     * Verifies that generate() can generate URLs with specific scheme.
     */
    public function testGenerateWithSpecificScheme(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('/api');
        $this->request->method('header')->with('host')->willReturn('example.com');
        $urlGenerator = $this->createUrlGenerator();
        $path = '/users';

        // Act
        $url = $urlGenerator->generate($path, 'http');

        // Assert
        $this->assertSame('http://example.com/api/users', $url, 'generate() should create absolute URL with specific scheme');
    }

    /**
     * Test generate with protocol-relative scheme.
     *
     * Verifies that generate() can generate protocol-relative URLs (//).
     */
    public function testGenerateWithProtocolRelativeScheme(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('');
        $this->request->method('header')->with('host')->willReturn('example.com');
        $urlGenerator = $this->createUrlGenerator();
        $path = '/users';

        // Act
        $url = $urlGenerator->generate($path, '//');

        // Assert
        $this->assertSame('//example.com/users', $url, 'generate() should create protocol-relative URL');
    }

    /**
     * Test generate handles empty path.
     *
     * Verifies that generate() handles empty path gracefully.
     */
    public function testGenerateHandlesEmptyPath(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('');
        $urlGenerator = $this->createUrlGenerator();

        // Act
        $url = $urlGenerator->generate('');

        // Assert
        $this->assertSame('', $url, 'generate() should handle empty path');
    }

    /**
     * Test generate handles path without leading slash.
     *
     * Verifies that generate() handles paths without leading slash.
     */
    public function testGenerateHandlesPathWithoutLeadingSlash(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('');
        $urlGenerator = $this->createUrlGenerator();

        // Act
        $url = $urlGenerator->generate('users');

        // Assert
        $this->assertSame('users', $url, 'generate() should handle path without leading slash');
    }

    /**
     * Test generate handles array with empty path.
     *
     * Verifies that generate() handles array format with empty path.
     */
    public function testGenerateHandlesArrayWithEmptyPath(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('');
        $urlGenerator = $this->createUrlGenerator();
        $args = ['', 'page' => 2];

        // Act
        $url = $urlGenerator->generate($args);

        // Assert
        $this->assertStringContainsString('page=2', $url, 'generate() should handle array with empty path');
    }

    /**
     * Test generate handles special characters in query parameters.
     *
     * Verifies that generate() properly encodes special characters in query parameters.
     */
    public function testGenerateHandlesSpecialCharactersInQuery(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('');
        $urlGenerator = $this->createUrlGenerator();
        $args = ['/users', 'search' => 'hello world', 'filter' => 'a&b'];

        // Act
        $url = $urlGenerator->generate($args);

        // Assert
        $this->assertStringContainsString('/users', $url);
        $this->assertStringContainsString('search=', $url);
        $this->assertStringContainsString('filter=', $url);
    }

    /**
     * Test generate with empty array.
     *
     * Verifies that generate() handles empty array input.
     */
    public function testGenerateWithEmptyArray(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('');
        $urlGenerator = $this->createUrlGenerator();
        $args = [];

        // Act
        $url = $urlGenerator->generate($args);

        // Assert
        $this->assertSame('', $url, 'generate() should return empty string for empty array');
    }

    /**
     * Test generate with prefix and query parameters.
     *
     * Verifies that generate() correctly combines prefix and query parameters.
     */
    public function testGenerateWithPrefixAndQueryParameters(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('/api/v1');
        $urlGenerator = $this->createUrlGenerator();
        $args = ['/users', 'page' => 2, 'limit' => 10];

        // Act
        $url = $urlGenerator->generate($args);

        // Assert
        $this->assertStringStartsWith('/api/v1/users', $url);
        $this->assertStringContainsString('page=2', $url);
        $this->assertStringContainsString('limit=10', $url);
    }

    /**
     * Test generate with absolute URL and query parameters.
     *
     * Verifies that generate() correctly combines absolute URL with query parameters.
     */
    public function testGenerateWithAbsoluteUrlAndQueryParameters(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('/api');
        $this->request->method('scheme')->willReturn('https');
        $this->request->method('header')->with('host')->willReturn('example.com');
        $urlGenerator = $this->createUrlGenerator();
        $args = ['/users', 'page' => 2];

        // Act
        $url = $urlGenerator->generate($args, true);

        // Assert
        $this->assertStringStartsWith('https://example.com/api/users', $url);
        $this->assertStringContainsString('page=2', $url);
    }

    /**
     * Test generate with fragment only (no query parameters).
     *
     * Verifies that generate() handles fragment without query parameters.
     */
    public function testGenerateWithFragmentOnly(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('');
        $urlGenerator = $this->createUrlGenerator();
        $args = ['/users', '#' => 'section1'];

        // Act
        $url = $urlGenerator->generate($args);

        // Assert
        $this->assertSame('/users#section1', $url, 'generate() should handle fragment without query parameters');
    }

    /**
     * Test generate with prefix and fragment.
     *
     * Verifies that generate() correctly combines prefix and fragment.
     */
    public function testGenerateWithPrefixAndFragment(): void
    {
        // Arrange
        $this->router->method('getPrefix')->willReturn('/api');
        $urlGenerator = $this->createUrlGenerator();
        $args = ['/users', 'page' => 2, '#' => 'section1'];

        // Act
        $url = $urlGenerator->generate($args);

        // Assert
        $this->assertStringStartsWith('/api/users', $url);
        $this->assertStringContainsString('page=2', $url);
        $this->assertStringEndsWith('#section1', $url);
    }

    public function testUrlReturnsPrefixRootWhenNoArgumentProvided(): void
    {
        $this->router->method('getPrefix')->with(null)->willReturn('/api');
        $urlGenerator = $this->createUrlGenerator();

        $this->assertSame('/api', $urlGenerator->url());
    }

    public function testUrlRejectsRelativeStringPath(): void
    {
        $urlGenerator = $this->createUrlGenerator();

        $this->expectException(InvalidUrlPathException::class);
        $this->expectExceptionMessage('url() path must start with "/"');

        $urlGenerator->url('login');
    }

    public function testUrlRejectsRelativeArrayPath(): void
    {
        $urlGenerator = $this->createUrlGenerator();

        $this->expectException(InvalidUrlPathException::class);
        $this->expectExceptionMessage('url() path must start with "/"');

        $urlGenerator->url(['login', 'tab' => 'sms']);
    }

    public function testActionResolvesRelativePathAgainstCurrentRequestParent(): void
    {
        $this->request->method('path')->willReturn('/api/admin/user/list');
        $this->router->method('getPrefix')->willReturnCallback(
            static fn (?string $uri = null): string => $uri === '/api/admin/user/list' ? '/api' : '/api'
        );
        $urlGenerator = $this->createUrlGenerator();

        $this->assertSame('/api/admin/user/login', $urlGenerator->action('login'));
    }

    public function testActionResolvesParentSegmentsAgainstCurrentRequestParent(): void
    {
        $this->request->method('path')->willReturn('/api/admin/user/list');
        $this->router->method('getPrefix')->willReturnCallback(
            static fn (?string $uri = null): string => $uri === '/api/admin/user/list' ? '/api' : '/api'
        );
        $urlGenerator = $this->createUrlGenerator();

        $this->assertSame('/api/admin/login', $urlGenerator->action('../login'));
    }

    public function testActionKeepsAbsoluteAppPath(): void
    {
        $this->router->method('getPrefix')->with(null)->willReturn('/api');
        $urlGenerator = $this->createUrlGenerator();

        $this->assertSame('/api/login', $urlGenerator->action('/login'));
    }

    public function testActionWithEmptyStringResolvesCurrentPathThenAppliesPrefix(): void
    {
        $this->request->method('path')->willReturn('/api/users/list');
        $this->router->method('getPrefix')->willReturn('/api');
        $urlGenerator = $this->createUrlGenerator();

        $this->assertSame('/api/users/list', $urlGenerator->action(''));
    }

    public function testUrlThrowsWhenArrayPathElementIsNotString(): void
    {
        $this->router->method('getPrefix')->willReturn('');
        $urlGenerator = $this->createUrlGenerator();

        $this->expectException(InvalidUrlPathException::class);
        $this->expectExceptionMessage('url() path must be a string');

        $urlGenerator->url([99, 'tab' => 'x']);
    }

    public function testActionThrowsWhenArrayPathElementIsNotString(): void
    {
        $this->router->method('getPrefix')->willReturn('');
        $urlGenerator = $this->createUrlGenerator();

        $this->expectException(InvalidUrlPathException::class);
        $this->expectExceptionMessage('action() path must be a string, got int.');

        $urlGenerator->action([404, 'x' => 1]);
    }

    public function testActionPreservesQueryStringOnResolvedRelativePath(): void
    {
        $this->request->method('path')->willReturn('/api/item/view');
        $this->router->method('getPrefix')->willReturn('/api');
        $urlGenerator = $this->createUrlGenerator();

        $this->assertSame('/api/item/edit?tab=1', $urlGenerator->action('edit?tab=1'));
    }
}
