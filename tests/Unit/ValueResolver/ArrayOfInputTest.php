<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\ValueResolver;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use ReflectionFunction;
use ReflectionParameter;
use Switon\Binding\InputBinder;
use Switon\Http\Attribute\RequestBody as RequestBodyAttribute;
use Switon\Http\RequestBodyResolver;
use Switon\Http\RequestInterface;
use Switon\Http\Tests\TestCase;
use Switon\Validating\Attribute\ArrayOf;
use Switon\Validating\Attribute\Length;
use Switon\Validating\Attribute\Min;
use Switon\Validating\Validation;
use Switon\Validating\ValidatorInterface;

/**
 * Test cases for ArrayOf attribute support in typed input binding.
 *
 * @group http
 * @group array-of-input
 */
#[AllowMockObjectsWithoutExpectations]
class ArrayOfInputTest extends TestCase
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
     * Test basic array of nested input objects population.
     */
    public function testBasicArrayOfInputs(): void
    {
        // Arrange
        $inputData = [
            'customerName' => 'John Doe',
            'items' => [
                ['productName' => 'Widget A', 'quantity' => 2, 'price' => 19.99],
                ['productName' => 'Widget B', 'quantity' => 1, 'price' => 29.99],
            ],
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnCallback(
            fn ($message, $placeholders) => $this->formatMessage($message, $placeholders)
        );

        $parameter = $this->createMockParameter(CreateOrderWithArrayRequest::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $this->assertInstanceOf(CreateOrderWithArrayRequest::class, $result);
        $this->assertSame('John Doe', $result->customerName);
        $this->assertIsArray($result->items);
        $this->assertCount(2, $result->items);

        $this->assertInstanceOf(OrderItemInput::class, $result->items[0]);
        $this->assertSame('Widget A', $result->items[0]->productName);
        $this->assertSame(2, $result->items[0]->quantity);
        $this->assertSame(19.99, $result->items[0]->price);

        $this->assertInstanceOf(OrderItemInput::class, $result->items[1]);
        $this->assertSame('Widget B', $result->items[1]->productName);
    }

    /**
     * Test array with minItems constraint validation.
     */
    public function testArrayMinItemsValidation(): void
    {
        // Arrange - empty array violates minItems: 1
        $inputData = [
            'customerName' => 'John',
            'items' => [],
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnCallback(
            fn ($message, $placeholders) => $this->formatMessage($message, $placeholders)
        );

        $parameter = $this->createMockParameter(CreateOrderWithArrayRequest::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $errors = $validation->getErrors();
        $this->assertArrayHasKey('items', $errors);
        $this->assertStringContainsString('at least', $errors['items']);
    }

    /**
     * Test array with maxItems constraint validation.
     */
    public function testArrayMaxItemsValidation(): void
    {
        // Arrange - 11 items violates maxItems: 10
        $inputData = [
            'customerName' => 'John',
            'items' => array_fill(0, 11, ['productName' => 'Item', 'quantity' => 1, 'price' => 10.00]),
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnCallback(
            fn ($message, $placeholders) => $this->formatMessage($message, $placeholders)
        );

        $parameter = $this->createMockParameter(CreateOrderWithArrayRequest::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $errors = $validation->getErrors();
        $this->assertArrayHasKey('items', $errors);
        $this->assertStringContainsString('at most', $errors['items']);
    }

    /**
     * Test nested validation in array items uses dot notation with index.
     */
    public function testArrayItemValidationWithDotNotation(): void
    {
        // Arrange
        $inputData = [
            'customerName' => 'John',
            'items' => [
                ['productName' => 'A', 'quantity' => 2, 'price' => 19.99],  // Name too short
                ['productName' => 'Valid Product', 'quantity' => -1, 'price' => 29.99],  // Negative quantity
            ],
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnCallback(
            fn ($message, $placeholders) => $this->formatMessage($message, $placeholders)
        );

        $parameter = $this->createMockParameter(CreateOrderWithArrayRequest::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $errors = $validation->getErrors();
        $this->assertArrayHasKey('items.0.productName', $errors);
        $this->assertArrayHasKey('items.1.quantity', $errors);
    }

    /**
     * Test array of scalar types (strings).
     */
    public function testArrayOfScalars(): void
    {
        // Arrange
        $inputData = [
            'title' => 'My Post',
            'tags' => ['php', 'framework', 'testing'],
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(CreatePostRequest::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $this->assertInstanceOf(CreatePostRequest::class, $result);
        $this->assertSame('My Post', $result->title);
        $this->assertIsArray($result->tags);
        $this->assertCount(3, $result->tags);
        $this->assertSame(['php', 'framework', 'testing'], $result->tags);
    }

    /**
     * Test array of integers.
     */
    public function testArrayOfIntegers(): void
    {
        // Arrange
        $inputData = [
            'title' => 'My Post',
            'tags' => [],
            'categoryIds' => [1, 2, 3, 5, 8],
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(CreatePostRequest::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $this->assertIsArray($result->categoryIds);
        $this->assertCount(5, $result->categoryIds);
        $this->assertSame([1, 2, 3, 5, 8], $result->categoryIds);
    }

    /**
     * Test empty array for optional array field.
     */
    public function testEmptyArrayForOptionalField(): void
    {
        // Arrange
        $inputData = [
            'title' => 'My Post',
            'tags' => [],  // Empty but provided
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(CreatePostRequest::class, 'request');

        // Act
        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        // Assert
        $this->assertIsArray($result->tags);
        $this->assertEmpty($result->tags);
    }

    /**
     * Test typed-input array item type mismatch uses indexed error path.
     */
    public function testArrayOfInputItemTypeMismatchUsesIndexedPath(): void
    {
        $inputData = [
            'customerName' => 'John',
            'items' => [
                'not-an-object-payload',
            ],
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(CreateOrderWithArrayRequest::class, 'request');

        $this->resolver->resolve($parameter, $parameter->getType()->getName());

        $errors = $validation->getErrors();
        $this->assertArrayHasKey('items.0', $errors);
    }

    /**
     * Test scalar array item type mismatch uses indexed error path.
     */
    public function testArrayOfScalarItemTypeMismatchUsesIndexedPath(): void
    {
        $inputData = [
            'title' => 'My Post',
            'tags' => [],
            'categoryIds' => [1, 'bad-int', 3],
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnArgument(0);

        $parameter = $this->createMockParameter(CreatePostRequest::class, 'request');

        $this->resolver->resolve($parameter, $parameter->getType()->getName());

        $errors = $validation->getErrors();
        $this->assertArrayHasKey('categoryIds.1', $errors);
    }

    /**
     * Test empty array does not fallback to non-empty default when minItems fails.
     */
    public function testArrayEmptyInputDoesNotFallbackToNonEmptyDefault(): void
    {
        $inputData = [
            'title' => 'Post',
            'tags' => [],
        ];

        $this->request->method('post')->willReturn($inputData);

        $validation = new Validation($this->validator, $inputData);
        $this->validator->method('beginValidate')->willReturn($validation);
        $this->validator->method('endValidate')->willReturnArgument(0);
        $this->validator->method('formatMessage')->willReturnCallback(
            fn ($message, $placeholders) => $this->formatMessage($message, $placeholders)
        );

        $parameter = $this->createMockParameter(CreatePostWithFallbackTagsRequest::class, 'request');

        $result = $this->resolver->resolve($parameter, $parameter->getType()->getName());

        $errors = $validation->getErrors();
        $this->assertArrayHasKey('tags', $errors);
        $this->assertStringContainsString('at least', $errors['tags']);
        $this->assertSame([], $result->tags);
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
class CreateOrderWithArrayRequest
{
    public string $customerName;

    #[ArrayOf(OrderItemInput::class, minItems: 1, maxItems: 10)]
    public array $items;
}

class OrderItemInput
{
    #[Length(2, 200)]
    public string $productName;

    #[Min(1)]
    public int $quantity;

    public float $price;
}

#[RequestBodyAttribute]
class CreatePostRequest
{
    public string $title;

    #[ArrayOf('string')]
    public array $tags = [];

    #[ArrayOf('int', minItems: 1)]
    public array $categoryIds = [];
}

#[RequestBodyAttribute]
class CreatePostWithFallbackTagsRequest
{
    public string $title;

    #[ArrayOf('string', minItems: 1)]
    public array $tags = ['fallback'];
}
