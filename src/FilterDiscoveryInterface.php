<?php

declare(strict_types=1);

namespace Switon\Http;

/**
 * Contract for discovering HTTP filters.
 *
 * Guidance: `discover()` returns candidate FQCNs only; enablement and order are handled later by the request pipeline.
 *
 * Road-signs:
 * - discover() returns filter class names only
 * - no enablement, aliasing, or order decisions here
 * - RequestHandler consumes the final configured filter list
 *
 * @see \Switon\Http\FilterDiscovery
 * @see \Switon\Http\RequestHandler
 */
interface FilterDiscoveryInterface
{
    /**
     * @return list<string>
     * Return discovered filter class names.
     */
    public function discover(): array;
}
