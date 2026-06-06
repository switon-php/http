<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Filters;

use ReflectionMethod;
use Switon\Core\Attribute\Autowired;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Event\RequestValidating;
use Switon\Http\Filter\MappingValidatorFilter;
use Switon\Http\RequestInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\Attribute\GetMapping;
use Switon\Routing\Attribute\PostMapping;
use Switon\Routing\Exception\MethodNotAllowedException;

class MappingValidatorFilterTest extends TestCase
{
    #[Autowired] protected MappingValidatorFilter $filter;
    #[Autowired] protected RequestInterface $request;

    // setUp() is not needed - parent::setUp() automatically autowires this test case

    protected function createRequestEvent(
        array  $get = [],
        array  $post = [],
        array  $server = [],
        string $rawBody = '',
        array  $cookie = [],
        array  $files = []
    ): RequestReceived {
        return new RequestReceived(
            GET: $get,
            POST: $post,
            SERVER: $server,
            RAW_BODY: $rawBody,
            COOKIE: $cookie,
            FILES: $files
        );
    }

    public function testOnValidatingAllowsRequestWhenMethodMatchesAttribute(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $reflectionMethod = new ReflectionMethod($this, 'testGetAction');
        $event = new RequestValidating($reflectionMethod);

        $this->filter->onValidating($event);
        $this->assertTrue(true);
    }

    public function testOnValidatingThrowsExceptionWhenMethodDoesNotMatchAttribute(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST']
        );
        $this->request->onRequestReceived($requestEvent);

        $reflectionMethod = new ReflectionMethod($this, 'testGetAction');
        $event = new RequestValidating($reflectionMethod);

        $this->expectException(MethodNotAllowedException::class);
        $this->filter->onValidating($event);
    }

    public function testOnValidatingDoesNothingWhenMethodHasNoMappingAttributes(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($requestEvent);

        $reflectionMethod = new ReflectionMethod($this, 'testActionWithoutMapping');
        $event = new RequestValidating($reflectionMethod);

        $this->filter->onValidating($event);
        $this->assertTrue(true);
    }

    public function testOnValidatingAllowsRequestWhenMethodMatchesOneOfMultipleAttributes(): void
    {
        $requestEvent = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST']
        );
        $this->request->onRequestReceived($requestEvent);

        $reflectionMethod = new ReflectionMethod($this, 'testGetOrPostAction');
        $event = new RequestValidating($reflectionMethod);

        $this->filter->onValidating($event);
        $this->assertTrue(true);
    }

    #[GetMapping]
    protected function testGetAction(): void
    {
    }

    protected function testActionWithoutMapping(): void
    {
    }

    #[GetMapping]
    #[PostMapping]
    protected function testGetOrPostAction(): void
    {
    }
}
