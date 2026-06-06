<?php

declare(strict_types=1);

namespace Switon\Http\Attribute;

use Attribute;
use ReflectionMethod;
use Switon\Core\Attribute\Autowired;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Invocation\Attribute\Interceptor;

/**
 * Marks ETag directives for a controller action response.
 *
 * Guidance: Use a stable response field when one already represents freshness; fall back to body hash only when needed.
 *
 * Road-signs:
 * - field mode: use one response field as the ETag value
 * - body mode: hash full response content
 * - compare against If-None-Match
 * - match -> 304 + empty body
 *
 * @see \Switon\Invoking\Invoker::getInterceptors()
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ETag extends Interceptor
{
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;

    /**
     * @param string|null $field Response field used as the ETag value; null hashes the full response body.
     */
    public function __construct(
        public ?string $field = null
    ) {
    }

    public function postHandle(ReflectionMethod $method, mixed &$return): void
    {
        $content = $this->response->getContent();
        if ($content === null || $content === '') {
            return;
        }

        // Calculate ETag
        if ($this->field !== null) {
            // Use field value from response
            $data = json_decode($content, true);
            if (is_array($data) && isset($data[$this->field])) {
                $etag = (string)$data[$this->field];
            } else {
                // Field not found, fallback to content hash
                $etag = md5($content);
            }
        } else {
            // Use MD5 of entire content
            $etag = md5($content);
        }

        // Set ETag header
        $this->response->setHeader('ETag', "\"$etag\"");

        // Validate If-None-Match
        $ifNoneMatch = $this->request->header('if-none-match');
        if ($ifNoneMatch === "\"$etag\"") {
            $this->response->setStatus(304, 'Not Modified');
            $this->response->setContent('');
        }
    }
}
