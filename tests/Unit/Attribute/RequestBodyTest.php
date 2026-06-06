<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Attribute;

use ReflectionClass;
use Switon\Binding\Attribute\ResolvedBy;
use Switon\Http\Attribute\RequestBody;
use Switon\Http\RequestBodyResolver;
use Switon\Http\Tests\TestCase;
use Attribute;
use ReflectionAttribute;

/**
 * Test cases for RequestBody attribute class.
 *
 * @group http
 */
class RequestBodyTest extends TestCase
{
    /**
     * Test that RequestBody attribute can be applied to classes.
     */
    public function testRequestBodyAttributeCanBeApplied(): void
    {
        // Create a test class with the attribute
        $testClass = new #[RequestBody] class () {
            public function dummy()
            {
            }
        };

        // Check that the attribute is applied
        $reflection = new ReflectionClass($testClass);
        $attributes = $reflection->getAttributes(RequestBody::class);

        $this->assertCount(1, $attributes, 'RequestBody attribute should be applied to the class');
        $this->assertInstanceOf(RequestBody::class, $attributes[0]->newInstance());
    }

    /**
     * Test that RequestBody attribute has correct target.
     */
    public function testRequestBodyAttributeHasCorrectTarget(): void
    {
        $reflection = new ReflectionClass(RequestBody::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes, 'RequestBody should have Attribute declaration');

        $attribute = $attributes[0]->newInstance();
        $this->assertEquals(
            Attribute::TARGET_CLASS,
            $attribute->flags,
            'RequestBody should target classes only'
        );
    }

    /**
     * Test that RequestBody attribute instance can be created.
     */
    public function testRequestBodyAttributeInstance(): void
    {
        $attribute = new RequestBody();
        $this->assertInstanceOf(RequestBody::class, $attribute);
        $this->assertInstanceOf(ResolvedBy::class, $attribute);
        $this->assertSame(RequestBodyResolver::class, $attribute->getResolver());
    }

    public function testRequestBodyIsDiscoverableViaResolvedByInheritance(): void
    {
        $testClass = new #[RequestBody] class () {
            public function dummy()
            {
            }
        };

        $reflection = new ReflectionClass($testClass);
        $attributes = $reflection->getAttributes(ResolvedBy::class, ReflectionAttribute::IS_INSTANCEOF);

        $this->assertCount(1, $attributes);
        $this->assertInstanceOf(RequestBody::class, $attributes[0]->newInstance());
    }
}
