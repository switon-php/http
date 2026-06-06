<?php

declare(strict_types=1);

namespace Switon\Http\Response;

use Switon\Http\ResponseInterface;

/**
 * Attachment/download response renderer boundary.
 *
 * Guidance: Prefer the higher-level response attachment API at call sites; this interface owns download-header shaping.
 *
 * Road-signs:
 * - input: file path or in-memory content
 * - filename controls Content-Disposition
 * - content type falls back to octet-stream when omitted
 *
 * @see \Switon\Http\Response\AttachmentRenderer
 */
interface AttachmentRendererInterface
{
    /**
     * Render one downloadable attachment response.
     */
    public function render(mixed $content, string $filename, ?string $contentType = null): ResponseInterface;
}
