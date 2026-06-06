<?php

declare(strict_types=1);

namespace Switon\Http\Response;

use Switon\Http\ResponseInterface;

/**
 * CSV response renderer boundary.
 *
 * Guidance: Prefer ResponseInterface::csv() at call sites; this interface owns CSV shaping and download headers.
 *
 * Road-signs:
 * - input: row array + filename
 * - header source: explicit headers or auto-detect
 * - output: ResponseInterface with CSV body + attachment headers
 *
 * @see \Switon\Http\Response\CsvRenderer
 */
interface CsvRendererInterface
{
    /**
     * @param list<array<string, mixed>|object> $rows
     * @param string|list<string>|null $headers
     */
    public function render(array $rows, string $filename, null|string|array $headers = null): ResponseInterface;
}
