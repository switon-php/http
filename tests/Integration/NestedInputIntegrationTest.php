<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Integration;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Switon\Binding\ArgumentsBinderInterface;
use Switon\Core\InputInterface;
use Switon\Http\Attribute\RequestBody;
use Switon\Http\RequestInterface;
use Switon\Http\Tests\TestCase;
use Switon\Invoking\InvokerInterface;
use Switon\Validating\Attribute\Email;
use Switon\Validating\Attribute\Length;
use ReflectionMethod;

/**
 * Integration test demonstrating nested typed-input validation in a realistic scenario.
 *
 * @group http
 * @group integration
 * @group nested-input
 */
#[AllowMockObjectsWithoutExpectations]
class NestedInputIntegrationTest extends TestCase
{
    /**
     * Test a complete order creation flow with nested address input objects.
     *
     * This demonstrates a realistic e-commerce scenario where an order
     * contains nested shipping and billing address objects.
     */
    public function testOrderCreationWithNestedAddresses(): void
    {
        // Arrange - simulate JSON request body
        $requestData = [
            'customerName' => 'John Doe',
            'customerEmail' => 'john@example.com',
            'productName' => 'Premium Widget',
            'quantity' => 2,
            'shippingAddress' => [
                'street' => '123 Main Street',
                'city' => 'New York',
                'state' => 'NY',
                'zipCode' => '10001',
            ],
            'billingAddress' => [
                'street' => '456 Business Ave',
                'city' => 'Boston',
                'state' => 'MA',
                'zipCode' => '02101',
            ],
        ];

        // Create and register mock input
        $input = $this->createMock(InputInterface::class);
        $input->method('all')->willReturn($requestData);
        $input->method('get')->willReturnCallback(fn ($key) => $requestData[$key] ?? null);
        $input->method('has')->willReturnCallback(fn ($key) => array_key_exists($key, $requestData));

        $request = $this->createMock(RequestInterface::class);
        $request->method('post')->willReturn($requestData);

        // Register mock in container
        $this->container->set(InputInterface::class, $input);
        $this->container->set(RequestInterface::class, $request);

        $argumentsBinder = $this->container->get(ArgumentsBinderInterface::class);
        $invoker = $this->container->get(InvokerInterface::class);
        $controller = new OrderController();
        $arguments = $argumentsBinder->resolve(new ReflectionMethod($controller, 'createAction'));

        // Act - invoke the controller action
        $result = $invoker->invoke([$controller, 'createAction'], $arguments);

        // Assert - verify the order was created correctly
        $this->assertIsArray($result);
        $this->assertSame('success', $result['status']);
        $this->assertSame('John Doe', $result['order']['customer']);
        $this->assertSame('Premium Widget', $result['order']['product']);
        $this->assertSame('123 Main Street, New York, NY 10001', $result['order']['shippingTo']);
        $this->assertSame('456 Business Ave, Boston, MA 02101', $result['order']['billingTo']);
    }

    /**
     * Test optional billing address defaults to shipping address.
     */
    public function testOptionalBillingAddressDefaultsToShipping(): void
    {
        // Arrange - no billing address provided
        $requestData = [
            'customerName' => 'Jane Smith',
            'customerEmail' => 'jane@example.com',
            'productName' => 'Basic Widget',
            'quantity' => 1,
            'shippingAddress' => [
                'street' => '789 Test Lane',
                'city' => 'Seattle',
                'state' => 'WA',
                'zipCode' => '98101',
            ],
            // billingAddress not provided - should default to shipping
        ];

        $input = $this->createMock(InputInterface::class);
        $input->method('all')->willReturn($requestData);
        $input->method('get')->willReturnCallback(fn ($key) => $requestData[$key] ?? null);
        $input->method('has')->willReturnCallback(fn ($key) => array_key_exists($key, $requestData));

        $request = $this->createMock(RequestInterface::class);
        $request->method('post')->willReturn($requestData);

        $this->container->set(InputInterface::class, $input);
        $this->container->set(RequestInterface::class, $request);

        $argumentsBinder = $this->container->get(ArgumentsBinderInterface::class);
        $invoker = $this->container->get(InvokerInterface::class);
        $controller = new OrderController();
        $arguments = $argumentsBinder->resolve(new ReflectionMethod($controller, 'createAction'));

        // Act
        $result = $invoker->invoke([$controller, 'createAction'], $arguments);

        // Assert - billing address should match shipping
        $this->assertIsArray($result);
        $this->assertSame($result['order']['shippingTo'], $result['order']['billingTo']);
        $this->assertSame('789 Test Lane, Seattle, WA 98101', $result['order']['billingTo']);
    }
}

// Test fixtures - realistic e-commerce input objects

#[RequestBody]
class CreateOrderInput
{
    #[Length(3, 100)]
    public string $customerName;

    #[Email]
    public string $customerEmail;

    #[Length(2, 200)]
    public string $productName;

    public int $quantity;

    public AddressInput $shippingAddress;

    public ?AddressInput $billingAddress = null;
}

class AddressInput
{
    #[Length(5, 200)]
    public string $street;

    #[Length(2, 100)]
    public string $city;

    #[Length(2, 2)]
    public string $state;

    #[Length(5, 10)]
    public string $zipCode;
}

class OrderController
{
    public function createAction(CreateOrderInput $input): array
    {
        // Simulate order creation
        $shipping = $input->shippingAddress;
        $billing = $input->billingAddress ?? $shipping;

        return [
            'status' => 'success',
            'order' => [
                'customer' => $input->customerName,
                'product' => $input->productName,
                'quantity' => $input->quantity,
                'shippingTo' => "{$shipping->street}, {$shipping->city}, {$shipping->state} {$shipping->zipCode}",
                'billingTo' => "{$billing->street}, {$billing->city}, {$billing->state} {$billing->zipCode}",
            ],
        ];
    }
}
