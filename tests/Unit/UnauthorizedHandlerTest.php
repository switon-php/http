<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Core\Attribute\Autowired;
use Switon\Http\Exception\ForbiddenException;
use Switon\Http\Exception\UnauthorizedException;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\Tests\TestCase;
use Switon\Http\UnauthorizedHandler;
use Switon\Principal\Exception\NotAuthenticatedException;
use Switon\Routing\RouterInterface;
use RuntimeException;

use function urlencode;

/**
 * Test cases for UnauthorizedHandler component.
 *
 * Tests 401 Unauthorized exception handling and redirect logic.
 */
#[AllowMockObjectsWithoutExpectations]
class UnauthorizedHandlerTest extends TestCase
{
    #[Autowired] protected UnauthorizedHandler $handler;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected RouterInterface $router;

    protected function beforeSetUpHttpContainer(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
        $this->router = $this->createMock(RouterInterface::class);

        $this->container->remove(RequestInterface::class);
        $this->container->remove(ResponseInterface::class);
        $this->container->remove(RouterInterface::class);

        $this->container->replace(RequestInterface::class, $this->request);
        $this->container->replace(ResponseInterface::class, $this->response);
        $this->container->replace(RouterInterface::class, $this->router);
    }

    /**
     * Test handle() returns false for non-UnauthorizedException.
     */
    public function testHandleReturnsFalseForNonUnauthorizedException(): void
    {
        // Arrange
        $exception = ForbiddenException::of('Forbidden');

        // Act
        $result = $this->handler->handle($exception);

        // Assert
        $this->assertFalse($result, 'handle() should return false for non-UnauthorizedException');
    }

    public function testHandleReturnsFalseForGenericThrowable(): void
    {
        $result = $this->handler->handle(new RuntimeException('no'));

        $this->assertFalse($result);
    }

    /**
     * Test handle() redirects to login for non-JSON requests.
     */
    public function testHandleRedirectsToLoginForNonJsonRequests(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');

        $this->request->method('path')->willReturn('/dashboard');
        $this->request->method('get')->willReturnMap([
            ['redirect', '/dashboard', '/dashboard'],
        ]);

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/login?redirect=%2Fdashboard');

        // Act
        $result = $this->handler->handle($exception);

        // Assert
        $this->assertTrue($result, 'handle() should return true after redirect');
    }

    /**
     * Test handle() returns false for JSON requests.
     */
    public function testHandleReturnsFalseForJsonRequests(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');

        $this->request->method('wantsJson')->willReturn(true);

        $this->response->expects($this->never())
            ->method('redirect');

        // Act
        $result = $this->handler->handle($exception);

        // Assert
        $this->assertFalse($result, 'handle() should return false for JSON requests');
    }

    public function testHandleRedirectsForNotAuthenticatedExceptionWhenNonJson(): void
    {
        $exception = NotAuthenticatedException::of('Not authenticated');

        $this->request->method('wantsJson')->willReturn(false);
        $this->request->method('path')->willReturn('/app');
        $this->request->method('get')->willReturnMap([
            ['redirect', '/app', '/app'],
        ]);

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/login?redirect=%2Fapp');

        $this->assertTrue($this->handler->handle($exception));
    }

    public function testHandleReturnsFalseForNotAuthenticatedExceptionWhenWantsJson(): void
    {
        $exception = NotAuthenticatedException::of('Not authenticated');

        $this->request->method('wantsJson')->willReturn(true);

        $this->response->expects($this->never())->method('redirect');

        $this->assertFalse($this->handler->handle($exception));
    }

    /**
     * Test handle() uses the request path for redirect.
     */
    public function testHandleUsesRequestPathForRedirect(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');

        $this->request->method('wantsJson')->willReturn(false);
        $this->request->method('path')->willReturn('/custom-path');
        $this->request->method('get')->willReturnMap([
            ['redirect', '/custom-path', '/custom-path'],
        ]);

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/login?redirect=%2Fcustom-path');

        // Act
        $result = $this->handler->handle($exception);

        // Assert
        $this->assertTrue($result, 'handle() should use the request path');
    }

    /**
     * Test handle() uses request path when redirect parameter is empty.
     */
    public function testHandleUsesRequestPathWhenRedirectParameterIsEmpty(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');

        $this->request->method('path')->willReturn('/profile');
        $this->request->method('get')->willReturnMap([
            ['redirect', '/profile', '/profile'],
        ]);

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/login?redirect=%2Fprofile');

        // Act
        $result = $this->handler->handle($exception);

        // Assert
        $this->assertTrue($result, 'handle() should use request path when redirect parameter is empty');
    }

    /**
     * Test handle() redirects to login without query for root path.
     */
    public function testHandleRedirectsToLoginWithoutQueryForRootPath(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');

        $this->request->method('path')->willReturn('/');
        $this->request->method('get')->willReturnMap([
            ['redirect', '/', '/'],
        ]);

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/login');

        // Act
        $result = $this->handler->handle($exception);

        // Assert
        $this->assertTrue($result, 'handle() should redirect to /login without query for root path');
    }

    /**
     * Test handle() normalizes path by removing trailing slashes.
     */
    public function testHandleNormalizesPathByRemovingTrailingSlashes(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');

        $this->request->method('wantsJson')->willReturn(false);
        $this->request->method('path')->willReturn('/dashboard/');
        $this->request->method('get')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $key === 'redirect' ? '/dashboard' : $default
        );

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/login?redirect=%2Fdashboard');

        // Act
        $result = $this->handler->handle($exception);

        // Assert
        $this->assertTrue($result, 'handle() should normalize path by removing trailing slashes');
    }

    /**
     * Test handle() uses a normalized request path without a trailing slash.
     */
    public function testHandleUsesNormalizedRequestPathWithoutTrailingSlash(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');

        $this->request->method('wantsJson')->willReturn(false);
        $this->request->method('path')->willReturn('/dashboard');
        $this->request->method('get')->willReturnMap([
            ['redirect', '/dashboard', '/dashboard'],
        ]);

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/login?redirect=%2Fdashboard');

        // Act
        $result = $this->handler->handle($exception);

        // Assert
        $this->assertTrue($result, 'handle() should use the request path');
    }

    /**
     * Test handle() detects JSON from Accept header with charset.
     */
    public function testHandleDetectsJsonFromAcceptHeaderWithCharset(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');

        $this->request->method('wantsJson')->willReturn(true);

        $this->response->expects($this->never())
            ->method('redirect');

        // Act
        $result = $this->handler->handle($exception);

        // Assert
        $this->assertFalse($result, 'handle() should detect JSON from Accept header with charset');
    }

    /**
     * Test handle() detects JSON from Accept header with multiple types.
     */
    public function testHandleDetectsJsonFromAcceptHeaderWithMultipleTypes(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');

        $this->request->method('wantsJson')->willReturn(true);

        $this->response->expects($this->never())
            ->method('redirect');

        // Act
        $result = $this->handler->handle($exception);

        // Assert
        $this->assertFalse($result, 'handle() should detect JSON from Accept header with multiple types');
    }

    /**
     * Test handle() treats null Accept header as non-JSON.
     */
    public function testHandleTreatsNullAcceptHeaderAsNonJson(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');

        $this->request->method('path')->willReturn('/page');
        $this->request->method('get')->willReturnMap([
            ['redirect', '/page', '/page'],
        ]);

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/login?redirect=%2Fpage');

        // Act
        $result = $this->handler->handle($exception);

        // Assert
        $this->assertTrue($result, 'handle() should treat null Accept header as non-JSON');
    }

    /**
     * Test handle() uses custom redirect parameter.
     */
    public function testHandleUsesCustomRedirectParameter(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');

        $this->request->method('path')->willReturn('/original-path');
        $this->request->method('get')->willReturnCallback(function ($key, $default = null) {
            return match ($key) {
                'redirect' => '/custom-redirect',
                default => $default,
            };
        });

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/login?redirect=%2Fcustom-redirect');

        // Act
        $result = $this->handler->handle($exception);

        // Assert
        $this->assertTrue($result, 'handle() should use custom redirect parameter');
    }

    public function testHandleUrlEncodesRedirectParameterWithQueryString(): void
    {
        $exception = UnauthorizedException::of('Unauthorized');

        $this->request->method('wantsJson')->willReturn(false);
        $this->request->method('path')->willReturn('/any');
        $target = '/search?q=a&sort=1';
        $this->request->method('get')->willReturnMap([
            ['redirect', '/any', $target],
        ]);

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/login?redirect=' . urlencode($target));

        $this->assertTrue($this->handler->handle($exception));
    }

    /**
     * Test handle() normalizes a trailing slash before redirecting.
     */
    public function testHandleNormalizesTrailingSlashBeforeRedirect(): void
    {
        // Arrange
        $exception = UnauthorizedException::of('Unauthorized');

        $this->request->method('path')->willReturn('/dashboard/');
        $this->request->method('get')->willReturnCallback(function ($key, $default = null) {
            return match ($key) {
                'redirect' => '/dashboard',
                default => $default,
            };
        });

        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/login?redirect=%2Fdashboard');

        // Act
        $result = $this->handler->handle($exception);

        // Assert
        $this->assertTrue($result, 'handle() should normalize trailing slashes from the request path');
    }
}
