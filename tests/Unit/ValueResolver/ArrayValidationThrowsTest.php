<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\ValueResolver;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Binding\ArgumentsBinderInterface;
use Switon\Core\InputInterface;
use Switon\Http\Attribute\RequestBody;
use Switon\Http\RequestInterface;
use Switon\Http\Tests\TestCase;
use Switon\Invoking\InvokerInterface;
use Switon\Validating\Attribute\ArrayOf;
use Switon\Validating\Attribute\Length;
use Switon\Validating\Attribute\Min;
use Switon\Validating\Exception\ValidateFailedException;
use ReflectionMethod;

/**
 * Verify that validation errors cause exception to be thrown.
 *
 * @group http
 * @group array-of-input
 */
#[AllowMockObjectsWithoutExpectations]
class ArrayValidationThrowsTest extends TestCase
{
    /**
     * Test that array element validation errors throw ValidateFailedException.
     */
    public function testArrayElementValidationThrowsException(): void
    {
        // Arrange - some array elements are invalid
        $inputData = [
            'title' => 'Test Order',
            'items' => [
                ['name' => 'Valid Item', 'qty' => 5],
                ['name' => 'X', 'qty' => 2],        // name too short
                ['name' => 'Another Valid', 'qty' => -1],  // qty negative
            ],
        ];

        $input = $this->createMock(InputInterface::class);
        $input->method('all')->willReturn($inputData);
        $input->method('get')->willReturnCallback(fn ($key) => $inputData[$key] ?? null);
        $input->method('has')->willReturnCallback(fn ($key) => array_key_exists($key, $inputData));

        $request = $this->createMock(RequestInterface::class);
        $request->method('post')->willReturn($inputData);

        $this->container->set(InputInterface::class, $input);
        $this->container->set(RequestInterface::class, $request);

        $argumentsBinder = $this->container->get(ArgumentsBinderInterface::class);
        $invoker = $this->container->get(InvokerInterface::class);
        $controller = new TestOrderController();
        // Act & Assert - should throw exception, not return incomplete object
        $this->expectException(ValidateFailedException::class);

        try {
            $arguments = $argumentsBinder->resolve(new ReflectionMethod($controller, 'createAction'));
            $invoker->invoke([$controller, 'createAction'], $arguments);
            $this->fail('Expected ValidateFailedException to be thrown');
        } catch (ValidateFailedException $e) {
            // Verify that we have errors for the invalid elements
            $errors = $e->getErrors();
            $this->assertArrayHasKey('items.1.name', $errors);
            $this->assertArrayHasKey('items.2.qty', $errors);
            throw $e;  // Re-throw for expectException
        }
    }
}

// Test fixtures

#[RequestBody]
class TestOrderRequest
{
    public string $title;

    #[ArrayOf(TestOrderItemInput::class, minItems: 1)]
    public array $items;
}

class TestOrderItemInput
{
    #[Length(2, 100)]
    public string $name;

    #[Min(1)]
    public int $qty;
}

class TestOrderController
{
    public function createAction(TestOrderRequest $request): array
    {
        return ['success' => true];
    }
}
