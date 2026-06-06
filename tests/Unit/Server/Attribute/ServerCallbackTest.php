<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server\Attribute;

use Switon\Http\Server\Attribute\ServerCallback;
use Switon\Http\Tests\TestCase;

class ServerCallbackTest extends TestCase
{
    public function testServerCallbackAttributeCanBeInstantiated(): void
    {
        $attribute = new ServerCallback();

        $this->assertInstanceOf(ServerCallback::class, $attribute);
    }
}
