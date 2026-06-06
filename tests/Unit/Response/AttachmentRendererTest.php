<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Response;

use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\FileNotFoundException;
use Switon\Core\PathAliasInterface;
use Switon\Http\Event\RequestReceived;
use Switon\Http\RequestInterface;
use Switon\Http\Response\AttachmentRendererInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\ServerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;

use function basename;
use function file_exists;
use function file_put_contents;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AttachmentRendererTest extends TestCase
{
    #[Autowired] protected AttachmentRendererInterface $renderer;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected RequestInterface $request;

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->container->replace(RouterInterface::class, $this->createStub(RouterInterface::class));
        $this->container->replace(ServerInterface::class, $this->createStub(ServerInterface::class));

        // Property autowiring is automatically performed by parent::setUp()
    }

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

    public function testRenderSetsDownloadHeadersForContentString(): void
    {
        $content = 'file content';
        $filename = 'test.txt';

        $result = $this->renderer->render($content, $filename);

        $this->assertSame($this->response, $result);
        $this->assertSame('File Transfer', $this->response->getHeader('Content-Description'));
        $this->assertStringContainsString('attachment; filename=', $this->response->getHeader('Content-Disposition'));
        $this->assertSame('binary', $this->response->getHeader('Content-Transfer-Encoding'));
        $this->assertSame('must-revalidate', $this->response->getHeader('Cache-Control'));
        $this->assertSame('application/octet-stream', $this->response->getHeader('Content-Type'));
        $this->assertSame($content, $this->response->getContent());
    }

    public function testRenderUsesCustomContentTypeWhenProvided(): void
    {
        $content = 'file content';
        $filename = 'test.pdf';
        $contentType = 'application/pdf';

        $result = $this->renderer->render($content, $filename, $contentType);

        $this->assertSame($this->response, $result);
        $this->assertSame($contentType, $this->response->getHeader('Content-Type'));
    }

    public function testRenderHandlesIeUserAgentEncoding(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; MSIE 10.0)'
            ]
        );
        $this->request->onRequestReceived($event);

        $content = 'file content';
        $filename = 'test file.txt';

        $result = $this->renderer->render($content, $filename);

        $this->assertSame($this->response, $result);
        $disposition = $this->response->getHeader('Content-Disposition');
        $this->assertStringContainsString('test', $disposition);
        $this->assertStringContainsString('file.txt', $disposition);
        $this->assertStringNotContainsString('test file.txt', $disposition);
    }

    public function testRenderEncodesFilenameForTridentUserAgent(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 6.1; Trident/7.0; rv:11.0) like Gecko',
            ]
        );
        $this->request->onRequestReceived($event);

        $result = $this->renderer->render('x', 'a b.pdf');

        $this->assertSame($this->response, $result);
        $disposition = $this->response->getHeader('Content-Disposition');
        $this->assertStringContainsString('attachment; filename=', $disposition);
        $this->assertStringNotContainsString('a b.pdf', $disposition);
    }

    public function testRenderRaisesWhenResolvedFilePathIsMissing(): void
    {
        $missing = sys_get_temp_dir() . '/switon_attachment_missing_' . uniqid('', true) . '.txt';

        $this->expectException(FileNotFoundException::class);

        $this->renderer->render($missing, 'gone.txt');
    }

    public function testRenderHandlesFilePathWithPathAlias(): void
    {
        $tempFile = sys_get_temp_dir() . '/switon_test_' . uniqid() . '.txt';
        file_put_contents($tempFile, 'test content');

        try {
            $pathAlias = $this->container->get(PathAliasInterface::class);
            $pathAlias->set('@test', sys_get_temp_dir());

            $result = $this->renderer->render('@test/' . basename($tempFile), 'test.txt');

            $this->assertSame($this->response, $result);
            $this->assertTrue($this->response->hasFile());
            $this->assertNotSame(null, $this->response->getFile());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testRenderSetsContentLengthForFilePath(): void
    {
        $tempFile = sys_get_temp_dir() . '/switon_test_' . uniqid() . '.txt';
        $content = 'test content';
        file_put_contents($tempFile, $content);

        try {
            $pathAlias = $this->container->get(PathAliasInterface::class);
            $pathAlias->set('@test', sys_get_temp_dir());

            $result = $this->renderer->render('@test/' . basename($tempFile), 'test.txt');

            $this->assertSame($this->response, $result);
            $contentLength = $this->response->getHeader('Content-Length');
            $this->assertNotSame(null, $contentLength);
            $this->assertSame((string)strlen($content), $contentLength);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
