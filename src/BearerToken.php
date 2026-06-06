<?php

declare(strict_types=1);

namespace Switon\Http;

use Switon\Core\Attribute\Autowired;

use function count;
use function explode;
use function strcasecmp;

/**
 * Bearer token extractor for HTTP requests.
 *
 * Road-signs:
 * - header first: Authorization Bearer token
 * - fallback second: configured request input key
 * - invalid scheme or missing token -> null
 * - RequestInterface supplies header + input reads
 *
 * @see \Switon\Http\BearerTokenInterface
 * @see \Switon\Http\RequestInterface
 */
class BearerToken implements BearerTokenInterface
{
    #[Autowired] protected RequestInterface $request;

    #[Autowired] protected string $fallback = 'access_token';

    /**
     * Extract one bearer token from the current request.
     */
    public function extract(): ?string
    {
        if (($token = $this->request->header('authorization')) !== null) {
            $parts = explode(' ', $token, 2);
            // RFC 7235: scheme is case-insensitive
            if (count($parts) === 2 && strcasecmp($parts[0], 'Bearer') === 0) {
                return $parts[1];
            }
        }

        if ($this->fallback !== '' && ($token = $this->request->get($this->fallback)) !== null) {
            return $token;
        }

        return null;
    }
}
