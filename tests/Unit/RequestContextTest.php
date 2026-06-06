<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Core\ContextConnScoped;
use Switon\Http\RequestContext;
use Switon\Http\Tests\TestCase;

/**
 * Test cases for RequestContext component.
 *
 * Tests request context state isolation for FPM/Swoole compatibility.
 */
class RequestContextTest extends TestCase
{
    /**
     * Test RequestContext implements ContextConnScoped.
     */
    public function testRequestContextImplementsContextConnScoped(): void
    {
        // Arrange & Act
        $context = new RequestContext();

        // Assert
        $this->assertInstanceOf(ContextConnScoped::class, $context, 'RequestContext should implement ContextConnScoped');
    }

    /**
     * Test RequestContext has default empty arrays.
     */
    public function testRequestContextHasDefaultEmptyArrays(): void
    {
        // Arrange & Act
        $context = new RequestContext();

        // Assert
        $this->assertSame([], $context->_GET, '_GET should be empty array by default');
        $this->assertSame([], $context->_POST, '_POST should be empty array by default');
        $this->assertSame([], $context->_REQUEST, '_REQUEST should be empty array by default');
        $this->assertSame([], $context->_SERVER, '_SERVER should be empty array by default');
        $this->assertSame([], $context->_FILES, '_FILES should be empty array by default');
        $this->assertSame([], $context->headers, 'headers should be empty array by default');
        $this->assertSame([], $context->attributes, 'attributes should be empty array by default');
    }

    /**
     * Test RequestContext rawBody is uninitialized by default.
     */
    public function testRequestContextRawBodyIsUninitializedByDefault(): void
    {
        // Arrange & Act
        $context = new RequestContext();

        // Assert
        $this->assertFalse(isset($context->rawBody), 'rawBody should be uninitialized by default');
    }

    /**
     * Test RequestContext matcher is null by default.
     */
    public function testRequestContextMatcherIsNullByDefault(): void
    {
        // Arrange & Act
        $context = new RequestContext();

        // Assert
        $this->assertNull($context->matcher, 'matcher should be null by default');
    }

    /**
     * Test RequestContext allows setting _GET data.
     */
    public function testRequestContextAllowsSettingGetData(): void
    {
        // Arrange
        $context = new RequestContext();

        // Act
        $context->_GET = ['key' => 'value'];

        // Assert
        $this->assertSame(['key' => 'value'], $context->_GET, '_GET should be settable');
    }

    /**
     * Test RequestContext allows setting _POST data.
     */
    public function testRequestContextAllowsSettingPostData(): void
    {
        // Arrange
        $context = new RequestContext();

        // Act
        $context->_POST = ['name' => 'John'];

        // Assert
        $this->assertSame(['name' => 'John'], $context->_POST, '_POST should be settable');
    }

    /**
     * Test RequestContext allows setting _REQUEST data.
     */
    public function testRequestContextAllowsSettingRequestData(): void
    {
        // Arrange
        $context = new RequestContext();

        // Act
        $context->_REQUEST = ['id' => '123'];

        // Assert
        $this->assertSame(['id' => '123'], $context->_REQUEST, '_REQUEST should be settable');
    }

    /**
     * Test RequestContext allows setting _SERVER data.
     */
    public function testRequestContextAllowsSettingServerData(): void
    {
        // Arrange
        $context = new RequestContext();

        // Act
        $context->_SERVER = ['REQUEST_METHOD' => 'GET'];

        // Assert
        $this->assertSame(['REQUEST_METHOD' => 'GET'], $context->_SERVER, '_SERVER should be settable');
    }

    /**
     * Test RequestContext allows setting _FILES data.
     */
    public function testRequestContextAllowsSettingFilesData(): void
    {
        // Arrange
        $context = new RequestContext();

        // Act
        $context->_FILES = ['upload' => ['name' => 'file.txt']];

        // Assert
        $this->assertSame(['upload' => ['name' => 'file.txt']], $context->_FILES, '_FILES should be settable');
    }

    /**
     * Test RequestContext allows setting headers.
     */
    public function testRequestContextAllowsSettingHeaders(): void
    {
        // Arrange
        $context = new RequestContext();

        // Act
        $context->headers = ['Content-Type' => 'application/json'];

        // Assert
        $this->assertSame(['Content-Type' => 'application/json'], $context->headers, 'headers should be settable');
    }

    /**
     * Test RequestContext allows setting attributes.
     */
    public function testRequestContextAllowsSettingAttributes(): void
    {
        // Arrange
        $context = new RequestContext();

        // Act
        $context->attributes = ['user_id' => 42];

        // Assert
        $this->assertSame(['user_id' => 42], $context->attributes, 'attributes should be settable');
    }

    /**
     * Test RequestContext allows setting rawBody.
     */
    public function testRequestContextAllowsSettingRawBody(): void
    {
        // Arrange
        $context = new RequestContext();

        // Act
        $context->rawBody = '{"key":"value"}';

        // Assert
        $this->assertSame('{"key":"value"}', $context->rawBody, 'rawBody should be settable');
    }

    /**
     * Test RequestContext allows setting matcher.
     */
    public function testRequestContextAllowsSettingMatcher(): void
    {
        // Arrange
        $context = new RequestContext();
        $matcher = $this->createStub(\Switon\Routing\MatcherInterface::class);

        // Act
        $context->matcher = $matcher;

        // Assert
        $this->assertSame($matcher, $context->matcher, 'matcher should be settable');
    }

    /**
     * Test RequestContext isolates state between instances.
     */
    public function testRequestContextIsolatesStateBetweenInstances(): void
    {
        // Arrange
        $context1 = new RequestContext();
        $context2 = new RequestContext();

        // Act
        $context1->_GET = ['key1' => 'value1'];
        $context2->_GET = ['key2' => 'value2'];

        // Assert
        $this->assertSame(['key1' => 'value1'], $context1->_GET, 'context1 should have its own _GET data');
        $this->assertSame(['key2' => 'value2'], $context2->_GET, 'context2 should have its own _GET data');
    }
}
