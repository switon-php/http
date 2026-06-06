<?php

declare(strict_types=1);

namespace Switon\Http\Response;

use Switon\Http\ResponseInterface;

/**
 * JSON response renderer boundary.
 *
 * Guidance: Prefer ResponseInterface::json() at call sites; this interface defines the rendering boundary behind it.
 *
 * Road-signs:
 * - input: mixed payload
 * - option shape: JSON_* bitmask
 * - output: ResponseInterface with JSON body + content type
 *
 * @see \Switon\Http\Response\JsonRenderer
 */
interface JsonRendererInterface
{
    public function render(mixed $data, int $options = 0): ResponseInterface;
}
