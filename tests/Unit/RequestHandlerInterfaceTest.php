<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionClass;
use Switon\Http\RequestHandlerInterface;

class RequestHandlerInterfaceTest extends BaseTestCase
{
    public function testRequestHandlerInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(RequestHandlerInterface::class));
        $this->assertTrue(method_exists(RequestHandlerInterface::class, 'boot'));
        $this->assertTrue(method_exists(RequestHandlerInterface::class, 'handle'));
    }

    public function testRequestHandlerInterfaceBootMethodSignature(): void
    {
        $reflection = new ReflectionClass(RequestHandlerInterface::class);
        $method = $reflection->getMethod('boot');

        $this->assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        $this->assertNotSame(null, $returnType);
        $this->assertSame('void', $returnType->getName());

        $this->assertCount(0, $method->getParameters());
    }

    public function testRequestHandlerInterfaceHandleMethodSignature(): void
    {
        $reflection = new ReflectionClass(RequestHandlerInterface::class);
        $method = $reflection->getMethod('handle');

        $this->assertTrue($method->hasReturnType());
        $returnType = $method->getReturnType();
        $this->assertNotSame(null, $returnType);
        $this->assertSame('void', $returnType->getName());

        $this->assertCount(0, $method->getParameters());
    }
}
