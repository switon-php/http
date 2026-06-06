<?php

declare(strict_types=1);

namespace Switon\Http\Attribute;

use Attribute;
use ReflectionMethod;
use Switon\Core\Attribute\Autowired;
use Switon\Http\ResponseInterface;
use Switon\Invocation\Attribute\Interceptor;

/**
 * Marks Cache-Control directives for a controller action response.
 *
 * Guidance: Use `noStore` for truly uncacheable responses; otherwise prefer explicit `maxAge` with public/private intent.
 *
 * Road-signs:
 * - noStore wins and also implies no-cache
 * - noCache keeps caches but requires revalidation
 * - public/private controls shareability
 * - maxAge and sMaxAge set freshness windows
 * - pair with ETag when validators are available
 *
 * @see \Switon\Invoking\Invoker::getInterceptors()
 */
#[Attribute(Attribute::TARGET_METHOD)]
class CacheControl extends Interceptor
{
    #[Autowired] protected ResponseInterface $response;

    /**
     * @param int|null $maxAge Freshness lifetime in seconds.
     * @param bool $public Whether shared caches may store the response.
     * @param int|null $sMaxAge Shared-cache freshness override.
     * @param bool $mustRevalidate Whether stale responses must revalidate.
     * @param bool $noCache Whether caches must revalidate before reuse.
     * @param bool $noStore Whether all caching is disabled.
     */
    public function __construct(
        public ?int $maxAge = null,
        public bool $public = true,
        public ?int $sMaxAge = null,
        public bool $mustRevalidate = false,
        public bool $noCache = false,
        public bool $noStore = false,
    ) {
    }

    public function postHandle(ReflectionMethod $method, mixed &$return): void
    {
        $directives = [];

        // no-store takes precedence (disables all caching)
        if ($this->noStore) {
            $directives[] = 'no-store';
            $directives[] = 'no-cache';
        } elseif ($this->noCache) {
            $directives[] = 'no-cache';
        } else {
            // Public or private
            $directives[] = $this->public ? 'public' : 'private';

            // max-age
            if ($this->maxAge !== null) {
                $directives[] = "max-age=$this->maxAge";
            }

            // s-maxage (for shared caches like CDN)
            if ($this->sMaxAge !== null) {
                $directives[] = "s-maxage=$this->sMaxAge";
            }

            // must-revalidate
            if ($this->mustRevalidate) {
                $directives[] = 'must-revalidate';
            }
        }

        $cacheControl = implode(', ', $directives);
        $this->response->setHeader('Cache-Control', $cacheControl);
    }
}
