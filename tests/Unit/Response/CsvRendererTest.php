<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Response;

use Switon\Core\Attribute\Autowired;
use Switon\Http\Response\AttachmentRendererInterface;
use Switon\Http\Response\CsvRendererInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CsvRendererTest extends TestCase
{
    #[Autowired] protected CsvRendererInterface $renderer;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected AttachmentRendererInterface $attachmentRenderer;

    protected function beforeSetUpHttpContainer(): void
    {
        // Set up AttachmentRendererInterface mock BEFORE property autowiring to prevent container from resolving to real AttachmentRenderer
        // This ensures CsvRenderer (injected in parent::setUp()) gets the mock instead of real AttachmentRenderer instance
        $this->attachmentRenderer = $this->createMock(AttachmentRendererInterface::class);
        $this->container->remove(AttachmentRendererInterface::class);
        $this->container->replace(AttachmentRendererInterface::class, $this->attachmentRenderer);
    }

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));

        // AttachmentRendererInterface is already set in beforeSetUpHttpContainer()
        // Property autowiring is automatically performed by parent::setUp()
    }

    public function testRenderRendersArrayDataAsCsv(): void
    {
        $rows = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25]
        ];

        $this->attachmentRenderer->expects($this->once())
            ->method('render')
            ->with(
                $this->stringContains('John'),
                'test.csv',
                'text/csv; charset=utf-8'
            )
            ->willReturn($this->response);

        $result = $this->renderer->render($rows, 'test.csv');

        $this->assertSame($this->response, $result);
    }

    public function testRenderAddsCsvExtensionIfMissing(): void
    {
        $rows = [['name' => 'John']];

        $this->attachmentRenderer->expects($this->once())
            ->method('render')
            ->with(
                $this->anything(),
                'test.csv',
                'text/csv; charset=utf-8'
            )
            ->willReturn($this->response);

        $this->renderer->render($rows, 'test');
    }

    public function testRenderUsesCustomHeadersWhenProvidedAsString(): void
    {
        $rows = [
            ['John', 30],
            ['Jane', 25]
        ];

        $this->attachmentRenderer->expects($this->once())
            ->method('render')
            ->willReturn($this->response);

        $this->renderer->render($rows, 'test.csv', 'name,age');
    }

    public function testRenderUsesCustomHeadersWhenProvidedAsArray(): void
    {
        $rows = [
            ['John', 30],
            ['Jane', 25]
        ];

        $this->attachmentRenderer->expects($this->once())
            ->method('render')
            ->willReturn($this->response);

        $this->renderer->render($rows, 'test.csv', ['name', 'age']);
    }

    public function testRenderAutoDetectsHeadersFromFirstRow(): void
    {
        $rows = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25]
        ];

        $this->attachmentRenderer->expects($this->once())
            ->method('render')
            ->willReturn($this->response);

        $this->renderer->render($rows, 'test.csv', null);
    }
}
