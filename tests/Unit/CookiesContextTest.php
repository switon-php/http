<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Http\CookiesContext;
use Switon\Http\Tests\TestCase;

/**
 * Test cases for CookiesContext component.
 *
 * Tests cookies context state isolation for FPM/Swoole compatibility.
 */
class CookiesContextTest extends TestCase
{
    /**
     * Test CookiesContext has default empty cookies array.
     */
    public function testCookiesContextHasDefaultEmptyCookiesArray(): void
    {
        // Arrange & Act
        $context = new CookiesContext();

        // Assert
        $this->assertSame([], $context->cookies, 'cookies should be empty array by default');
    }

    /**
     * Test CookiesContext allows setting cookies.
     */
    public function testCookiesContextAllowsSettingCookies(): void
    {
        // Arrange
        $context = new CookiesContext();

        // Act
        $context->cookies = ['session' => 'abc123', 'user' => 'john'];

        // Assert
        $this->assertSame(['session' => 'abc123', 'user' => 'john'], $context->cookies, 'cookies should be settable');
    }

    /**
     * Test CookiesContext allows adding individual cookies.
     */
    public function testCookiesContextAllowsAddingIndividualCookies(): void
    {
        // Arrange
        $context = new CookiesContext();

        // Act
        $context->cookies['session'] = 'abc123';
        $context->cookies['user'] = 'john';

        // Assert
        $this->assertSame('abc123', $context->cookies['session'], 'individual cookie should be settable');
        $this->assertSame('john', $context->cookies['user'], 'individual cookie should be settable');
        $this->assertCount(2, $context->cookies, 'cookies should have 2 entries');
    }

    /**
     * Test CookiesContext allows removing cookies.
     */
    public function testCookiesContextAllowsRemovingCookies(): void
    {
        // Arrange
        $context = new CookiesContext();
        $context->cookies = ['session' => 'abc123', 'user' => 'john'];

        // Act
        unset($context->cookies['session']);

        // Assert
        $this->assertArrayNotHasKey('session', $context->cookies, 'cookie should be removable');
        $this->assertArrayHasKey('user', $context->cookies, 'other cookies should remain');
    }

    /**
     * Test CookiesContext isolates state between instances.
     */
    public function testCookiesContextIsolatesStateBetweenInstances(): void
    {
        // Arrange
        $context1 = new CookiesContext();
        $context2 = new CookiesContext();

        // Act
        $context1->cookies = ['session1' => 'abc'];
        $context2->cookies = ['session2' => 'xyz'];

        // Assert
        $this->assertSame(['session1' => 'abc'], $context1->cookies, 'context1 should have its own cookies');
        $this->assertSame(['session2' => 'xyz'], $context2->cookies, 'context2 should have its own cookies');
    }

    /**
     * Test CookiesContext allows checking cookie existence.
     */
    public function testCookiesContextAllowsCheckingCookieExistence(): void
    {
        // Arrange
        $context = new CookiesContext();
        $context->cookies = ['session' => 'abc123'];

        // Act & Assert
        $this->assertTrue(isset($context->cookies['session']), 'isset should work for existing cookie');
        $this->assertFalse(isset($context->cookies['nonexistent']), 'isset should return false for non-existing cookie');
    }

    /**
     * Test CookiesContext allows iterating over cookies.
     */
    public function testCookiesContextAllowsIteratingOverCookies(): void
    {
        // Arrange
        $context = new CookiesContext();
        $context->cookies = ['cookie1' => 'value1', 'cookie2' => 'value2', 'cookie3' => 'value3'];

        // Act
        $keys = [];
        $values = [];
        foreach ($context->cookies as $key => $value) {
            $keys[] = $key;
            $values[] = $value;
        }

        // Assert
        $this->assertSame(['cookie1', 'cookie2', 'cookie3'], $keys, 'should iterate over cookie keys');
        $this->assertSame(['value1', 'value2', 'value3'], $values, 'should iterate over cookie values');
    }

    /**
     * Test CookiesContext allows counting cookies.
     */
    public function testCookiesContextAllowsCountingCookies(): void
    {
        // Arrange
        $context = new CookiesContext();

        // Act & Assert
        $this->assertCount(0, $context->cookies, 'empty context should have 0 cookies');

        $context->cookies = ['cookie1' => 'value1', 'cookie2' => 'value2'];
        $this->assertCount(2, $context->cookies, 'context should have 2 cookies');
    }

    /**
     * Test CookiesContext allows clearing all cookies.
     */
    public function testCookiesContextAllowsClearingAllCookies(): void
    {
        // Arrange
        $context = new CookiesContext();
        $context->cookies = ['cookie1' => 'value1', 'cookie2' => 'value2'];

        // Act
        $context->cookies = [];

        // Assert
        $this->assertSame([], $context->cookies, 'cookies should be clearable');
        $this->assertCount(0, $context->cookies, 'cleared context should have 0 cookies');
    }

    /**
     * Test CookiesContext handles special characters in cookie values.
     */
    public function testCookiesContextHandlesSpecialCharactersInCookieValues(): void
    {
        // Arrange
        $context = new CookiesContext();

        // Act
        $context->cookies = [
            'special' => 'value with spaces',
            'encoded' => 'value%20with%20encoding',
            'unicode' => '值',
        ];

        // Assert
        $this->assertSame('value with spaces', $context->cookies['special']);
        $this->assertSame('value%20with%20encoding', $context->cookies['encoded']);
        $this->assertSame('值', $context->cookies['unicode']);
    }
}
