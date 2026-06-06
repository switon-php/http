<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted when an HTTP request is received.
 *
 * Road-signs:
 * - transport adapters emit raw superglobal-style payloads here
 * - RequestAdjust runs next
 * - Request and Cookies read the normalized input later
 *
 * Log category: <code>switon.http.request.received</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onRequest()
 * @see \Switon\Http\Server\Adapter\Php::prepareGlobals()
 * @see \Switon\Http\Server\Adapter\Fpm::prepareGlobals()
 * @see \Switon\Http\Request
 * @see \Switon\Http\Cookies
 * @see \Switon\Http\Event\RequestAdjust
 */
#[EventLevel(Severity::DEBUG)]
class RequestReceived implements JsonSerializable
{
    /**
     * @param array<string, mixed> $GET
     * @param array<string, mixed> $POST
     * @param array<string, mixed> $SERVER
     * @param array<string, mixed> $COOKIE
     * @param array<string, mixed> $FILES
     */
    public function __construct(
        public array   $GET,
        public array   $POST,
        public array   $SERVER,
        public ?string $RAW_BODY,
        public array   $COOKIE,
        public array   $FILES
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'method' => $this->SERVER['REQUEST_METHOD'] ?? '',
            'uri' => $this->SERVER['REQUEST_URI'] ?? '',
            'query' => $this->SERVER['QUERY_STRING'] ?? '',
            'has_post' => !empty($this->POST),
            'has_files' => !empty($this->FILES),
            'has_cookie' => !empty($this->COOKIE),
            'content_type' => $this->SERVER['CONTENT_TYPE'] ?? '',
        ];
    }
}
