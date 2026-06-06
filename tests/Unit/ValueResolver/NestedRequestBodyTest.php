<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\ValueResolver;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionFunction;
use ReflectionParameter;
use Switon\Binding\Exception\InputBindingNestingDepthExceededException;
use Switon\Binding\InputBinder;
use Switon\Http\Attribute\RequestBody as RequestBodyAttribute;
use Switon\Http\RequestBodyResolver;
use Switon\Http\RequestInterface;
use Switon\Http\Tests\TestCase;
use Switon\Validating\Attribute\Length;
use Switon\Validating\Attribute\Required;
use Switon\Validating\Validation;
use Switon\Validating\ValidatorInterface;
use DateTimeImmutable;

/**
 * Test cases for nested input support in RequestBody resolver.
 *
 * @group http
 * @group nested-input
 */
#[AllowMockObjectsWithoutExpectations]
class NestedRequestBodyTest extends TestCase
{
    protected RequestBodyResolver $resolver;
    protected RequestInterface $request;
    protected ValidatorInterface $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = $this->createMock(RequestInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        // Create resolver with mocked dependencies
        $inputBinder = $this->container->make(InputBinder::class, ['validator' => $this->validator]);

        $this->resolver = new class ($this->request, $inputBinder) extends RequestBodyResolver {
            public function __construct(
                RequestInterface $request,
                InputBinder $inputBinder,
            ) {
                $this->request = $request;
                $this->inputBinder = $inputBinder;
            }
        };
    }

    /**
     * Test that nested input is populated from input data.
     */
    public function testNestedInputPopulation(): void
    {
        // Arrange
        $inputData = [
            'productName' => 'Widget',
            'shippingAddress' => [
                'street' => '123 Main St',
                'city' => 'New York',
            ],
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(CreateOrderInput::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $this->assertInstanceOf(CreateOrderInput::class, $result);
        $this->assertSame('Widget', $result->productName);
        $this->assertInstanceOf(AddressInput::class, $result->shippingAddress);
        $this->assertSame('123 Main St', $result->shippingAddress->street);
        $this->assertSame('New York', $result->shippingAddress->city);
    }

    /**
     * Test that optional nested input can be null.
     */
    public function testOptionalNestedInputCanBeNull(): void
    {
        // Arrange
        $inputData = [
            'productName' => 'Widget',
            'shippingAddress' => [
                'street' => '123 Main St',
                'city' => 'New York',
            ],
            // billingAddress is optional and not provided
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(CreateOrderInput::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $this->assertNull($result->billingAddress);
    }

    /**
     * Test that explicit null for optional nested input is handled.
     */
    public function testExplicitNullForOptionalNestedInput(): void
    {
        // Arrange
        $inputData = [
            'productName' => 'Widget',
            'shippingAddress' => [
                'street' => '123 Main St',
                'city' => 'New York',
            ],
            'billingAddress' => null,  // Explicitly null
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(CreateOrderInput::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $this->assertNull($result->billingAddress);
    }

    /**
     * Test that nested input validation errors use dot notation.
     */
    public function testNestedInputValidationUsesPath(): void
    {
        // Arrange
        $inputData = [
            'productName' => 'A',  // Too short
            'shippingAddress' => [
                'street' => 'B',   // Too short
                'city' => 'NYC',
            ],
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnCallback(
            fn ($message, $placeholders) => $this->formatMessage($message, $placeholders)
        );

        $parameter = $this->createMockParameter(CreateOrderInput::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $errors = $validation->getErrors();
        $this->assertArrayHasKey('productName', $errors);
        $this->assertArrayHasKey('shippingAddress.street', $errors);
    }

    /**
     * Test that required nested input generates validation error when missing.
     */
    public function testRequiredNestedInputValidation(): void
    {
        // Arrange
        $inputData = [
            'productName' => 'Widget',
            // shippingAddress is missing (required)
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(CreateOrderInput::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $errors = $validation->getErrors();
        $this->assertArrayHasKey('shippingAddress', $errors);
    }

    /**
     * Test that explicit Required on nested input runs when field is missing.
     */
    public function testExplicitRequiredNestedInputValidation(): void
    {
        // Arrange
        $inputData = [
            'productName' => 'Widget',
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(CreateOrderInputWithExplicitRequiredNestedInput::class, 'request');

        // Act
        $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $errors = $validation->getErrors();
        $this->assertArrayHasKey('shippingAddress', $errors);
        $this->assertSame('You must provide shippingAddress', $errors['shippingAddress']);
    }

    /**
     * Test that non-array nested input adds a type error.
     */
    public function testNestedInputTypeMismatchUsesValidationError(): void
    {
        // Arrange
        $inputData = [
            'productName' => 'Widget',
            'shippingAddress' => 'invalid',
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(CreateOrderInput::class, 'request');

        // Act
        $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $errors = $validation->getErrors();
        $this->assertArrayHasKey('shippingAddress', $errors);
    }

    /**
     * Test that deeply nested input objects are populated correctly.
     */
    public function testDeeplyNestedInputPopulation(): void
    {
        // Arrange
        $inputData = [
            'userName' => 'john',
            'profile' => [
                'bio' => 'Developer',
                'address' => [
                    'street' => '456 Oak St',
                    'city' => 'Boston',
                ],
            ],
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(CreateUserInput::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $this->assertInstanceOf(CreateUserInput::class, $result);
        $this->assertSame('john', $result->userName);
        $this->assertInstanceOf(ProfileInput::class, $result->profile);
        $this->assertSame('Developer', $result->profile->bio);
        $this->assertInstanceOf(AddressInput::class, $result->profile->address);
        $this->assertSame('456 Oak St', $result->profile->address->street);
        $this->assertSame('Boston', $result->profile->address->city);
    }

    /**
     * Test that nesting depth limit prevents infinite recursion.
     */
    public function testNestingDepthLimit(): void
    {
        // This test verifies the MAX_NESTING_DEPTH constant
        // In practice, 10 levels of nesting should be more than sufficient
        $this->expectException(InputBindingNestingDepthExceededException::class);
        $this->expectExceptionMessage('Input nesting depth exceeds maximum');

        // Create deeply nested data (11 levels)
        $data = ['value' => 'test'];
        for ($i = 0; $i < 11; $i++) {
            $data = ['nested' => $data];
        }

        $this->request->method('post')->willReturn($data);

        $validation = new Validation($this->validator, $data);
        $this->validator->method('beginValidate')->willReturn($validation);

        $parameter = $this->createMockParameter(DeeplyNestedInput::class, 'request');

        // Act - should throw exception
        $this->resolver->resolve($parameter, $parameter->getType()->getName());
    }

    /**
     * Test current DateTimeImmutable behavior: unsupported Type validator target.
     */
    public function testDateTimeImmutableTypeValidationIsUnsupported(): void
    {
        $this->expectException(\Switon\Validating\Exception\UnsupportedValidationTypeException::class);
        $this->expectExceptionMessage('Unsupported validation type "DateTimeImmutable"');

        $inputData = [
            'eventName' => 'launch',
            'occurredAt' => ['date' => '2026-01-01 10:00:00'],
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(EventWithDateTimeInput::class, 'request');

        $this->resolver->resolve($parameter, $parameter->getType()->getName());
    }

    /**
     * Helper method to create a mock ReflectionParameter.
     */
    protected function createMockParameter(string $typeName, string $paramName): ReflectionParameter
    {
        $fn = eval("return function ({$typeName} \${$paramName}) {}; ");
        return (new ReflectionFunction($fn))->getParameters()[0];
    }

    protected function formatMessage(string $message, array $placeholders): string
    {
        return str_replace(
            array_map(fn ($key) => "{{$key}}", array_keys($placeholders)),
            array_map(
                fn ($value) => is_scalar($value) || $value === null ? (string)$value : (json_encode($value) ?: ''),
                array_values($placeholders)
            ),
            $message
        );
    }
}

// Test fixtures

#[RequestBodyAttribute]
class CreateOrderInput
{
    #[Length(2, 100)]
    public string $productName;

    public AddressInput $shippingAddress;

    public ?AddressInput $billingAddress = null;
}

class AddressInput
{
    #[Length(5, 200)]
    public string $street;

    public string $city;
}

#[RequestBodyAttribute]
class CreateUserInput
{
    public string $userName;

    public ProfileInput $profile;
}

#[RequestBodyAttribute]
class CreateOrderInputWithExplicitRequiredNestedInput
{
    public string $productName;

    #[Required(message: 'You must provide shippingAddress')]
    public AddressInput $shippingAddress;
}

class ProfileInput
{
    public string $bio;

    public AddressInput $address;
}

#[RequestBodyAttribute]
class DeeplyNestedInput
{
    public ?DeeplyNestedInput $nested = null;

    public string $value = '';
}

#[RequestBodyAttribute]
class EventWithDateTimeInput
{
    public string $eventName;
    public DateTimeImmutable $occurredAt;
}
