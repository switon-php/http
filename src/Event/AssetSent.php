<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted after an asset response is sent.
 *
 * Road-signs:
 * - emitted after static asset handling completes
 * - carries requested URI and final status code
 * - pairs with AssetSending
 *
 * Log category: <code>switon.http.response.asset_sent</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onRequest()
 * @see \Switon\Http\Server\StaticHandlerInterface
 * @see \Switon\Http\Event\AssetSending
 */
#[EventLevel(Severity::DEBUG)]
class AssetSent implements JsonSerializable
{
    /**
     * @param string $uri Requested URI.
     * @param int $statusCode Final HTTP status code.
     */
    public function __construct(
        public string $uri,
        public int    $statusCode,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'uri' => $this->uri,
            'status_code' => $this->statusCode,
        ];
    }
}
