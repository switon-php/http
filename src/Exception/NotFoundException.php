<?php

declare(strict_types=1);

namespace Switon\Http\Exception;

use Switon\Core\Exception;
use Switon\Core\NotFoundInterface;

/**
 * HTTP 404 base exception.
 *
 * Road-signs:
 * - route miss → NotFoundRouteException
 * - handler miss → ControllerNotFound/ActionNotFound
 * - record miss → Query/Orm NotFound
 * - check verb + prefix + normalized path
 * - routes registered via RouteRegistrar/RequestMapping
 *
 * @see \Switon\Core\Exception
 * @see \Switon\Http\RequestHandlerInterface
 * @see \Switon\Http\RequestHandler
 * @see \Switon\Routing\Router::match()
 * @see \Switon\Routing\Exception\NotFoundRouteException
 * @see \Switon\Routing\Event\RouteNotFound
 * @see \Switon\Routing\RouteRegistrar
 * @see \Switon\Query\Exception\NotFoundException
 * @see \Switon\Orm\Exception\EntityNotFoundException
 */
class NotFoundException extends Exception implements NotFoundInterface
{
    public function getStatusCode(): int
    {
        return 404;
    }
}
