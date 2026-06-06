<?php

declare(strict_types=1);

namespace Switon\Http\Response;

use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\FileNotFoundException;
use Switon\Core\FilesystemInterface;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;

use function str_contains;
use function urlencode;

/**
 * Attachment response renderer.
 *
 * Guidance: Pass either a resolved file path or final attachment content; this renderer prepares download headers and response body state.
 *
 * Road-signs:
 * - normalizes download headers
 * - supports file path input and raw content input
 * - legacy IE user agents get encoded filenames
 *
 * @see \Switon\Http\Response\AttachmentRendererInterface
 * @see \Switon\Http\ResponseInterface::setFile()
 * @see \Switon\Core\Exception\FileNotFoundException
 */
class AttachmentRenderer implements AttachmentRendererInterface
{
    #[Autowired] protected FilesystemInterface $filesystem;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected RequestInterface $request;

    /**
     * Render one downloadable attachment response.
     */
    public function render(mixed $content, string $filename, ?string $contentType = null): ResponseInterface
    {
        // Handle IE encoding issue
        if ($user_agent = $this->request->header('user-agent')) {
            if (str_contains($user_agent, 'Trident') || str_contains($user_agent, 'MSIE')) {
                $filename = urlencode($filename);
            }
        }

        // Set download headers
        $this->response->setHeader('Content-Description', 'File Transfer');
        $this->response->setHeader('Content-Disposition', 'attachment; filename=' . $filename);
        $this->response->setHeader('Content-Transfer-Encoding', 'binary');
        $this->response->setHeader('Cache-Control', 'must-revalidate');
        $this->response->setHeader('Content-Type', $contentType ?? 'application/octet-stream');

        // Handle content (file path string or content string)
        if (is_string($content) && (str_contains($content, '/') || str_contains($content, '\\') || str_starts_with($content, '@'))) {
            // It's a file path
            $this->response->setFile($content);
            $filePath = $this->response->getFile();
            if ($filePath !== null && is_file($filePath)) {
                $fileSize = $this->filesystem->size($filePath);
                $this->response->setHeader('Content-Length', (string)($fileSize ?? 0));
            } elseif ($filePath !== null) {
                FileNotFoundException::raise('Attachment file "{file}" not found', ['file' => $content]);
            }
        } else {
            // It's content
            $this->response->setContent($content);
        }

        return $this->response;
    }
}
