<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use JsonSerializable;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;

/**
 * Event emitted before an asset response is sent.
 *
 * Road-signs:
 * - emitted before a static asset leaves the server
 * - uri identifies the requested asset path
 * - AssetSent follows after completion
 *
 * Log category: <code>switon.http.response.asset_sending</code>
 *
 * @see \Switon\Http\Server\Adapter\Swoole::onRequest()
 * @see \Switon\Http\Server\StaticHandlerInterface
 * @see \Switon\Http\Event\AssetSent
 */
#[EventLevel(Severity::DEBUG)]
class AssetSending implements JsonSerializable
{
    /**
     * @param string $uri Requested URI.
     */
    public function __construct(
        public string $uri,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'uri' => $this->uri,
        ];
    }
}
