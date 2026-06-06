<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Http\BearerToken;
use Switon\Http\RequestInterface;
use Switon\Http\Tests\TestCase;

/**
 * Test cases for BearerToken component.
 *
 * Tests token extraction from Authorization header and query parameter.
 */
#[AllowMockObjectsWithoutExpectations]
class BearerTokenTest extends TestCase
{
    /**
     * Test extract() returns null when no token is present.
     *
     * Verifies that extract() returns null when neither Authorization header
     * nor access_token query parameter is present.
     */
    public function testExtractReturnsNullWhenNoToken(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $request->method('header')->with('authorization')->willReturn(null);
        $request->method('get')->with('access_token')->willReturn(null);

        $bearerToken = new BearerToken();
        $this->container->replace(RequestInterface::class, $request);
        $this->injector->inject($bearerToken);

        // Act
        $result = $bearerToken->extract();

        // Assert
        $this->assertNull($result, 'extract() should return null when no token is present');
    }

    /**
     * Test extract() extracts token from Authorization header.
     *
     * Verifies that extract() correctly extracts Bearer token from Authorization header.
     */
    public function testExtractExtractsTokenFromAuthorizationHeader(): void
    {
        // Arrange
        $token = 'test-token-123';
        $request = $this->createMock(RequestInterface::class);
        $request->method('header')->with('authorization')->willReturn('Bearer ' . $token);

        $bearerToken = new BearerToken();
        $this->container->replace(RequestInterface::class, $request);
        $this->injector->inject($bearerToken);

        // Act
        $result = $bearerToken->extract();

        // Assert
        $this->assertSame($token, $result, 'extract() should extract token from Authorization header');
    }

    public function testExtractAcceptsCaseInsensitiveBearerScheme(): void
    {
        $token = 'opaque-token';
        $request = $this->createMock(RequestInterface::class);
        $request->method('header')->with('authorization')->willReturn('bearer ' . $token);

        $bearerToken = new BearerToken();
        $this->container->replace(RequestInterface::class, $request);
        $this->injector->inject($bearerToken);

        $this->assertSame($token, $bearerToken->extract());
    }

    public function testExtractPreservesSpacesInsideBearerTokenValue(): void
    {
        $token = 'part-one part-two';
        $request = $this->createMock(RequestInterface::class);
        $request->method('header')->with('authorization')->willReturn('Bearer ' . $token);

        $bearerToken = new BearerToken();
        $this->container->replace(RequestInterface::class, $request);
        $this->injector->inject($bearerToken);

        $this->assertSame($token, $bearerToken->extract());
    }

    /**
     * Test extract() extracts token from query parameter.
     *
     * Verifies that extract() correctly extracts token from access_token query parameter
     * when Authorization header is not present.
     */
    public function testExtractExtractsTokenFromQueryParameter(): void
    {
        // Arrange
        $token = 'query-token-456';
        $request = $this->createMock(RequestInterface::class);
        $request->method('header')->with('authorization')->willReturn(null);
        $request->method('get')->with('access_token')->willReturn($token);

        $bearerToken = new BearerToken();
        $this->container->replace(RequestInterface::class, $request);
        $this->injector->inject($bearerToken);

        // Act
        $result = $bearerToken->extract();

        // Assert
        $this->assertSame($token, $result, 'extract() should extract token from query parameter');
    }

    /**
     * Test extract() prefers Authorization header over query parameter.
     *
     * Verifies that extract() returns token from Authorization header when both
     * Authorization header and query parameter are present.
     */
    public function testExtractPrefersAuthorizationHeaderOverQueryParameter(): void
    {
        // Arrange
        $headerToken = 'header-token-789';
        $queryToken = 'query-token-789';
        $request = $this->createMock(RequestInterface::class);
        $request->method('header')->with('authorization')->willReturn('Bearer ' . $headerToken);

        $bearerToken = new BearerToken();
        $this->container->replace(RequestInterface::class, $request);
        $this->injector->inject($bearerToken);

        // Act
        $result = $bearerToken->extract();

        // Assert
        $this->assertSame($headerToken, $result, 'extract() should prefer Authorization header over query parameter');
        $this->assertNotSame($queryToken, $result, 'extract() should not return query token when header token exists');
    }

    /**
     * Test extract() ignores invalid Authorization header format.
     *
     * Verifies that extract() returns null for invalid Authorization header format
     * and falls back to query parameter.
     */
    public function testExtractIgnoresInvalidAuthorizationHeader(): void
    {
        // Arrange
        $token = 'fallback-token';
        $request = $this->createMock(RequestInterface::class);
        $request->method('header')->with('authorization')->willReturn('InvalidFormat token');
        $request->method('get')->with('access_token')->willReturn($token);

        $bearerToken = new BearerToken();
        $this->container->replace(RequestInterface::class, $request);
        $this->injector->inject($bearerToken);

        // Act
        $result = $bearerToken->extract();

        // Assert
        $this->assertSame($token, $result, 'extract() should fall back to query parameter when Authorization header format is invalid');
    }

    /**
     * Test extract() ignores Authorization header without Bearer scheme.
     *
     * Verifies that extract() returns null when Authorization header exists but
     * does not start with "Bearer".
     */
    public function testExtractIgnoresNonBearerAuthorizationHeader(): void
    {
        // Arrange
        $token = 'fallback-token';
        $request = $this->createMock(RequestInterface::class);
        $request->method('header')->with('authorization')->willReturn('Basic dGVzdDp0ZXN0');
        $request->method('get')->with('access_token')->willReturn($token);

        $bearerToken = new BearerToken();
        $this->container->replace(RequestInterface::class, $request);
        $this->injector->inject($bearerToken);

        // Act
        $result = $bearerToken->extract();

        // Assert
        $this->assertSame($token, $result, 'extract() should fall back to query parameter when Authorization header is not Bearer');
    }

    /**
     * Test extract() handles Authorization header with only scheme.
     *
     * Verifies that extract() returns null when Authorization header contains
     * only "Bearer" without token value.
     */
    public function testExtractHandlesAuthorizationHeaderWithOnlyScheme(): void
    {
        // Arrange
        $token = 'fallback-token';
        $request = $this->createMock(RequestInterface::class);
        $request->method('header')->with('authorization')->willReturn('Bearer');
        $request->method('get')->with('access_token')->willReturn($token);

        $bearerToken = new BearerToken();
        $this->container->replace(RequestInterface::class, $request);
        $this->injector->inject($bearerToken);

        // Act
        $result = $bearerToken->extract();

        // Assert
        $this->assertSame($token, $result, 'extract() should fall back to query parameter when Authorization header has no token value');
    }

    /**
     * Test extract() uses custom fallback parameter name.
     *
     * Verifies that extract() correctly uses custom fallback parameter name when configured.
     */
    public function testExtractUsesCustomFallbackParameterName(): void
    {
        // Arrange
        $token = 'custom-token';
        $request = $this->createMock(RequestInterface::class);
        $request->method('header')->with('authorization')->willReturn(null);
        $request->method('get')->with('token')->willReturn($token);

        $bearerToken = new BearerToken();
        $this->container->replace(RequestInterface::class, $request);
        $this->injector->inject($bearerToken, ['fallback' => 'token']);

        // Act
        $result = $bearerToken->extract();

        // Assert
        $this->assertSame($token, $result, 'extract() should use custom fallback parameter name');
    }

    /**
     * Test extract() disables fallback when fallback is empty string.
     *
     * Verifies that extract() does not check request parameter when fallback is set to empty string.
     */
    public function testExtractDisablesFallbackWhenEmptyString(): void
    {
        // Arrange
        $request = $this->createMock(RequestInterface::class);
        $request->method('header')->with('authorization')->willReturn(null);

        $bearerToken = new BearerToken();
        $this->container->replace(RequestInterface::class, $request);
        $this->injector->inject($bearerToken, ['fallback' => '']);

        // Act
        $result = $bearerToken->extract();

        // Assert
        $this->assertNull($result, 'extract() should not use fallback when fallback is empty string');
    }
}
