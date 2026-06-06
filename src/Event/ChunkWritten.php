<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Switon\Http\ResponseInterface;

use function strlen;

/**
 * Event emitted after a response chunk is written.
 *
 * Road-signs:
 * - emitted after one streaming chunk is sent
 * - carries the final chunk text and send result
 * - pairs with ChunkWriting
 *
 * Log category: <code>switon.http.chunk.written</code>
 *
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\Event\ChunkWriting
 * @see \Switon\Http\AbstractServer::write()
 * @see \Switon\Http\Server\Adapter\Swoole::write()
 */
#[EventLevel(Severity::DEBUG)]
class ChunkWritten implements JsonSerializable
{
    /**
     * @param ResponseInterface $response Response object.
     * @param string $chunk Sent chunk text.
     * @param bool $result Whether the send succeeded.
     */
    public function __construct(
        public ResponseInterface $response,
        public string            $chunk,
        public bool              $result
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'chunk_length' => strlen($this->chunk),
            'result' => $this->result,
        ];
    }
}
