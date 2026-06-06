<?php

declare(strict_types=1);

namespace Switon\Http\Response;

use Switon\Http\ResponseInterface;

/**
 * XML response renderer boundary.
 *
 * Guidance: Prefer ResponseInterface::xml() at call sites; renderer options belong to XML shape, not transport flow.
 *
 * Road-signs:
 * - input: mixed payload
 * - option shape: root / version / encoding / item / format
 * - output: ResponseInterface with XML body + content type
 *
 * @see \Switon\Http\Response\XmlRenderer
 */
interface XmlRendererInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function render(mixed $data, array $options = []): ResponseInterface;
}
