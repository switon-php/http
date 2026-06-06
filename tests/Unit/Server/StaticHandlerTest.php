<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server;

use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionClass;
use Switon\Http\Server\StaticHandlerInterface;

class StaticHandlerTest extends BaseTestCase
{
    public function testStaticHandlerInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(StaticHandlerInterface::class));
        $this->assertTrue(method_exists(StaticHandlerInterface::class, 'isFile'));
        $this->assertTrue(method_exists(StaticHandlerInterface::class, 'getFile'));
        $this->assertTrue(method_exists(StaticHandlerInterface::class, 'getMimeType'));
    }

    public function testStaticHandlerInterfaceIsFileMethodSignature(): void
    {
        $reflection = new ReflectionClass(StaticHandlerInterface::class);
        $method = $reflection->getMethod('isFile');

        $this->assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        $this->assertNotSame(null, $returnType);
        $this->assertSame('bool', $returnType->getName());

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertSame('uri', $parameters[0]->getName());
        $this->assertTrue($parameters[0]->getType() !== null);
        $this->assertSame('string', $parameters[0]->getType()->getName());
    }

    public function testStaticHandlerInterfaceGetFileMethodSignature(): void
    {
        $reflection = new ReflectionClass(StaticHandlerInterface::class);
        $method = $reflection->getMethod('getFile');

        $this->assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        $this->assertNotSame(null, $returnType);
        $this->assertSame('string', $returnType->getName());
        $this->assertTrue($returnType->allowsNull());

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertSame('uri', $parameters[0]->getName());
        $this->assertTrue($parameters[0]->getType() !== null);
        $this->assertSame('string', $parameters[0]->getType()->getName());
    }

    public function testStaticHandlerInterfaceGetMimeTypeMethodSignature(): void
    {
        $reflection = new ReflectionClass(StaticHandlerInterface::class);
        $method = $reflection->getMethod('getMimeType');

        $this->assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        $this->assertNotSame(null, $returnType);
        $this->assertSame('string', $returnType->getName());

        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertSame('file', $parameters[0]->getName());
        $this->assertTrue($parameters[0]->getType() !== null);
        $this->assertSame('string', $parameters[0]->getType()->getName());
    }
}
