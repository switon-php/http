<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Response;

use JsonSerializable;
use Switon\Core\Attribute\Autowired;
use Switon\Http\Response\JsonRendererInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class JsonRendererTest extends TestCase
{
    #[Autowired] protected JsonRendererInterface $renderer;
    #[Autowired] protected ResponseInterface $response;

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));

        // Property autowiring is automatically performed by parent::setUp()
    }

    public function testRenderRendersArrayDataAsJson(): void
    {
        $data = ['key' => 'value', 'number' => 123];

        $result = $this->renderer->render($data);

        $this->assertSame($this->response, $result);
        $this->assertSame('application/json; charset=utf-8', $this->response->getHeader('Content-Type'));
        $content = $this->response->getContent();
        $this->assertIsString($content);
        $decoded = json_decode($content, true);
        $this->assertSame($data, $decoded);
    }

    public function testRenderRendersStringDataAsJson(): void
    {
        $data = 'simple string';

        $result = $this->renderer->render($data);

        $this->assertSame($this->response, $result);
        $content = $this->response->getContent();
        $this->assertSame('"simple string"', $content);
    }

    public function testRenderAutoWrapsJsonSerializableObjects(): void
    {
        $data = new class () implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['id' => 1, 'name' => 'Test'];
            }
        };

        $result = $this->renderer->render($data);

        $this->assertSame($this->response, $result);
        $content = $this->response->getContent();
        $decoded = json_decode($content, true);
        $this->assertArrayHasKey('code', $decoded);
        $this->assertArrayHasKey('msg', $decoded);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertSame(0, $decoded['code']);
        $this->assertSame('', $decoded['msg']);
        $this->assertSame(['id' => 1, 'name' => 'Test'], $decoded['data']);
    }

    public function testRenderUsesCustomOptionsWhenProvided(): void
    {
        $data = ['key' => 'value'];

        $result = $this->renderer->render($data, JSON_PRETTY_PRINT);

        $this->assertSame($this->response, $result);
        $content = $this->response->getContent();
        $this->assertStringContainsString("\n", $content);
    }

    public function testRenderUsesDefaultOptionsWhenOptionsIsZero(): void
    {
        $data = ['key' => 'value'];

        $result = $this->renderer->render($data, 0);

        $this->assertSame($this->response, $result);
        $content = $this->response->getContent();
        $this->assertIsString($content);
    }

    public function testRenderEncodesScalarNonArrayPayload(): void
    {
        $result = $this->renderer->render(2048);

        $this->assertSame($this->response, $result);
        $this->assertSame('2048', $this->response->getContent());
    }

    public function testRenderEncodesPlainObjectPayload(): void
    {
        $result = $this->renderer->render((object)['a' => 1]);

        $this->assertSame($this->response, $result);
        $content = $this->response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('"a"', $content);
        $this->assertStringContainsString('1', $content);
    }
}
