<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Response;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ClockInterface;
use Switon\Http\Response\XmlRendererInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;
use stdClass;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class XmlRendererTest extends TestCase
{
    #[Autowired] protected XmlRendererInterface $renderer;
    #[Autowired] protected ResponseInterface $response;

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));
        $this->container->replace(ClockInterface::class, $this->createStub(ClockInterface::class));

        // Property autowiring is automatically performed by parent::setUp()
    }

    public function testRenderRendersArrayDataAsXml(): void
    {
        $data = ['name' => 'John', 'age' => 30];

        $result = $this->renderer->render($data);

        $this->assertSame($this->response, $result);
        $this->assertSame('application/xml; charset=utf-8', $this->response->getHeader('Content-Type'));
        $content = $this->response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('<root>', $content);
        $this->assertStringContainsString('<name>John</name>', $content);
        $this->assertStringContainsString('<age>30</age>', $content);
    }

    public function testRenderRendersScalarValueAsXml(): void
    {
        $data = 'simple text';

        $result = $this->renderer->render($data);

        $this->assertSame($this->response, $result);
        $content = $this->response->getContent();
        $this->assertStringContainsString('<root>simple text</root>', $content);
    }

    public function testRenderUsesCustomRootElementName(): void
    {
        $data = ['key' => 'value'];

        $result = $this->renderer->render($data, ['root' => 'data']);

        $this->assertSame($this->response, $result);
        $content = $this->response->getContent();
        $this->assertStringContainsString('<data>', $content);
        $this->assertStringNotContainsString('<root>', $content);
    }

    public function testRenderHandlesNestedArrays(): void
    {
        $data = ['user' => ['name' => 'John', 'age' => 30]];

        $result = $this->renderer->render($data);

        $this->assertSame($this->response, $result);
        $content = $this->response->getContent();
        $this->assertStringContainsString('<user>', $content);
        $this->assertStringContainsString('<name>John</name>', $content);
        $this->assertStringContainsString('<age>30</age>', $content);
    }

    public function testRenderRecursesNestedObjectPayload(): void
    {
        $nested = new stdClass();
        $nested->role = 'admin';
        $data = new stdClass();
        $data->user = $nested;

        $result = $this->renderer->render($data);

        $this->assertSame($this->response, $result);
        $content = $this->response->getContent();
        $this->assertStringContainsString('<user>', $content);
        $this->assertStringContainsString('<role>admin</role>', $content);
    }

    public function testRenderHandlesNumericArrayKeysWithItemName(): void
    {
        $data = ['item1', 'item2'];

        $result = $this->renderer->render($data);

        $this->assertSame($this->response, $result);
        $content = $this->response->getContent();
        $this->assertStringContainsString('<item>item1</item>', $content);
        $this->assertStringContainsString('<item>item2</item>', $content);
    }

    public function testRenderFormatsOutputWhenFormatOptionIsTrue(): void
    {
        $data = ['name' => 'John'];

        $result = $this->renderer->render($data, ['format' => true]);

        $this->assertSame($this->response, $result);
        $content = $this->response->getContent();
        $this->assertIsString($content);
    }

    public function testRenderHandlesSpecialCharactersInXml(): void
    {
        $data = ['message' => '<tag>content</tag>'];

        $result = $this->renderer->render($data);

        $this->assertSame($this->response, $result);
        $content = $this->response->getContent();
        $this->assertStringContainsString('&lt;tag&gt;', $content);
        $this->assertStringContainsString('&lt;/tag&gt;', $content);
    }
}
