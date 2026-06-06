<?php

declare(strict_types=1);

namespace Switon\Http;

/**
 * Bearer token extractor for the current HTTP request.
 *
 * Guidance: Prefer the Authorization header; keep request-parameter fallback only for compatibility paths.
 *
 * Road-signs:
 * - primary source: Authorization Bearer token
 * - optional fallback: request input parameter
 * - invalid or missing token -> null
 * - RequestInterface is the data source
 *
 * @see \Switon\Http\BearerToken
 * @see \Switon\Http\RequestInterface
 */
interface BearerTokenInterface
{
    /**
     * Extract one bearer token from the request.
     */
    public function extract(): ?string;
}
