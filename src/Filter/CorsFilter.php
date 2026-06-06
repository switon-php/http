<?php

declare(strict_types=1);

namespace Switon\Http\Filter;

use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\StopFlow;
use Switon\Eventing\Attribute\EventListener;
use Switon\Http\Event\HeadersSending;
use Switon\Http\Event\RequestBegin;
use Switon\Http\RequestInterface;

use function strpos;
use function substr;

/**
 * Applies CORS headers and preflight handling to HTTP responses.
 *
 * Guidance: Keep origin policy explicit in production; wildcard fallback is a convenience, not a security model.
 *
 * Road-signs:
 * - RequestBegin short-circuits OPTIONS preflight
 * - HeadersSending writes CORS headers for cross-origin requests
 * - prod mode narrows same-root domains before falling back to `*`
 *
 * @see \Switon\Http\Event\RequestBegin
 * @see \Switon\Http\Event\HeadersSending
 * @see \Switon\Core\StopFlow
 */
class CorsFilter
{
    #[Autowired] protected AppInterface $app;
    #[Autowired] protected RequestInterface $request;

    #[Autowired] protected int $max_age = 86400;
    #[Autowired] protected ?string $origin;
    #[Autowired] protected bool $credentials = true;


    /**
     * Stop the pipeline for OPTIONS preflight requests.
     */
    /** @noinspection PhpUnusedParameterInspection */
    #[EventListener] public function onBegin(RequestBegin $event): void
    {
        if ($this->request->isVerb('OPTIONS')) {
            throw StopFlow::because('OPTIONS request handled');
        }
    }

    /**
     * Add CORS headers to the response when the request is cross-origin.
     */
    #[EventListener] public function onResponseHeadersSending(HeadersSending $event): void
    {
        $origin = $this->request->origin();
        if ($origin === '') {
            return;
        }

        // Extract host from origin (e.g., 'https://example.com' -> 'example.com')
        $originHost = parse_url($origin, PHP_URL_HOST);
        $host = $this->request->header('host');
        // Remove port from host header if present (e.g., 'example.com:8080' -> 'example.com')
        if ($host !== null && ($colonPos = strpos($host, ':')) !== false) {
            $host = substr($host, 0, $colonPos);
        }

        if (!is_string($originHost) || $originHost === '' || !is_string($host) || $host === '') {
            return;
        }

        if ($originHost !== $host) {
            if ($this->origin) {
                $allow_origin = $this->origin;
            } elseif ($this->app->isEnv('prod')) {
                $origin_pos = strpos($originHost, '.');
                $host_pos = strpos($host, '.');

                if (($origin_pos !== false && $host_pos !== false)
                    && substr($originHost, $origin_pos) === substr($host, $host_pos)
                ) {
                    $allow_origin = $origin;
                } else {
                    $allow_origin = '*';
                }
            } else {
                $allow_origin = $origin;
            }

            $allow_headers = 'Origin, Accept, Authorization, Content-Type, X-Requested-With';
            $allow_methods = 'HEAD,GET,POST,PUT,DELETE';
            $event->response
                ->setHeader('Access-Control-Allow-Origin', $allow_origin)
                ->setHeader('Access-Control-Allow-Credentials', $this->credentials ? 'true' : 'false')
                ->setHeader('Access-Control-Allow-Headers', $allow_headers)
                ->setHeader('Access-Control-Allow-Methods', $allow_methods)
                ->setHeader('Access-Control-Max-Age', (string)$this->max_age);
        }
    }
}
