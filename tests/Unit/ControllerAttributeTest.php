<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Http\Attribute\CacheControl;
use Switon\Http\Tests\TestCase;

class ControllerAttributeTest extends TestCase
{
    public function testCacheControlAttributeCanBeInstantiated(): void
    {
        $attribute = new CacheControl(3600);

        $this->assertSame(3600, $attribute->maxAge);
        $this->assertTrue($attribute->public);
    }

    public function testCacheControlAttributeCanBeInstantiatedWithCustomValues(): void
    {
        $attribute = new CacheControl(maxAge: 1800, public: false, mustRevalidate: true);

        $this->assertSame(1800, $attribute->maxAge);
        $this->assertFalse($attribute->public);
        $this->assertTrue($attribute->mustRevalidate);
    }

}
