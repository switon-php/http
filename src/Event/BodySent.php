<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Http\ResponseInterface;

use function strlen;

/**
 * Event emitted after a response body is sent.
 *
 * Road-signs:
 * - emitted after non-streaming body output completes
 * - carries final body length and send result
 * - pairs with BodySending
 *
 * Log category: <code>switon.http.body.sent</code>
 *
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\Event\BodySending
 * @see \Switon\Http\Server\Adapter\Native\Sender::sendBody()
 * @see \Switon\Http\Server\Adapter\Swoole::sendBody()
 */
#[EventLevel(Severity::DEBUG)]
class BodySent implements JsonSerializable
{
    /**
     * @param ResponseInterface $response Response object.
     * @param string $content Sent body text.
     * @param bool $result Whether the send succeeded.
     */
    public function __construct(
        public ResponseInterface $response,
        public string            $content,
        public bool              $result
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'content_length' => strlen($this->content),
            'result' => $this->result,
        ];
    }
}
