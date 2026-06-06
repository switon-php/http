<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Transformers;

use ReflectionMethod;
use Switon\Core\Attribute\Autowired;
use Switon\Http\Event\RequestInvoked;
use Switon\Http\Response\JsonRendererInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Http\Transformer\NormalizeActionReturnTransformer;
use Switon\Routing\RouterInterface;
use RuntimeException;
use stdClass;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class NormalizeActionReturnTransformerTest extends TestCase
{
    #[Autowired] protected NormalizeActionReturnTransformer $transformer;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected JsonRendererInterface $jsonRenderer;

    protected function beforeSetUpHttpContainer(): void
    {
        // Set up JsonRendererInterface mock BEFORE property autowiring to prevent container from resolving to real JsonRenderer
        // This ensures Response (injected in parent::setUp()) gets the mock instead of real JsonRenderer instance
        $this->jsonRenderer = $this->createMock(JsonRendererInterface::class);
        $this->container->remove(JsonRendererInterface::class);
        $this->container->replace(JsonRendererInterface::class, $this->jsonRenderer);
    }

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));

        // JsonRendererInterface is already set in beforeSetUpHttpContainer()
        // Property autowiring is automatically performed by parent::setUp()
    }

    protected function createDummyMethod(): ReflectionMethod
    {
        return new ReflectionMethod($this, 'setUp');
    }

    public function testOnInvokedNormalizesNullReturnValue(): void
    {
        $this->jsonRenderer->expects($this->once())
            ->method('render')
            ->with(['code' => 0, 'msg' => '']);

        $event = new RequestInvoked($this->createDummyMethod(), null);
        $this->transformer->onInvoked($event);
    }

    public function testOnInvokedNormalizesArrayReturnValue(): void
    {
        $data = ['key' => 'value'];
        $this->jsonRenderer->expects($this->once())
            ->method('render')
            ->with(['code' => 0, 'msg' => '', 'data' => $data]);

        $event = new RequestInvoked($this->createDummyMethod(), $data);
        $this->transformer->onInvoked($event);
    }

    public function testOnInvokedNormalizesStringReturnValueAsError(): void
    {
        $errorMessage = 'Error occurred';
        $this->jsonRenderer->expects($this->once())
            ->method('render')
            ->with(['code' => -1, 'msg' => $errorMessage]);

        $event = new RequestInvoked($this->createDummyMethod(), $errorMessage);
        $this->transformer->onInvoked($event);
    }

    public function testOnInvokedNormalizesIntReturnValue(): void
    {
        $code = 200;
        $this->jsonRenderer->expects($this->once())
            ->method('render')
            ->with(['code' => $code, 'msg' => '']);

        $event = new RequestInvoked($this->createDummyMethod(), $code);
        $this->transformer->onInvoked($event);
    }

    public function testOnInvokedDoesNothingForResponseReturnValue(): void
    {
        $this->jsonRenderer->expects($this->never())
            ->method('render');

        $event = new RequestInvoked($this->createDummyMethod(), $this->response);
        $this->transformer->onInvoked($event);
    }

    public function testOnInvokedThrowsThrowableReturnValue(): void
    {
        $exception = new RuntimeException('Test exception');

        $this->jsonRenderer->expects($this->never())
            ->method('render');

        $event = new RequestInvoked($this->createDummyMethod(), $exception);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test exception');
        $this->transformer->onInvoked($event);
    }

    public function testOnInvokedNormalizesOtherReturnValue(): void
    {
        $object = new stdClass();
        $object->property = 'value';

        $this->jsonRenderer->expects($this->once())
            ->method('render')
            ->with(['code' => 0, 'msg' => '', 'data' => $object]);

        $event = new RequestInvoked($this->createDummyMethod(), $object);
        $this->transformer->onInvoked($event);
    }

    public function testOnInvokedSkipsNormalizationWhenResponseHasContent(): void
    {
        $this->response->setContent('Already set');

        $this->jsonRenderer->expects($this->never())
            ->method('render');

        $event = new RequestInvoked($this->createDummyMethod(), ['key' => 'value']);
        $this->transformer->onInvoked($event);
    }
}
