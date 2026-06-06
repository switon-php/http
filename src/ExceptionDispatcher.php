<?php

declare(strict_types=1);

namespace Switon\Http;

use Switon\Core\Attribute\Autowired;
use Switon\Core\NotFoundInterface;
use Switon\Core\StopFlow;
use Switon\Http\Exception\ForbiddenException;
use Switon\Http\Exception\UnauthorizedException;
use Switon\Principal\Exception\NotAuthenticatedException;
use Throwable;

use function class_implements;
use function get_parent_class;

/**
 * Picks a handler from $handlers and calls handle().
 *
 * Road-signs:
 * - #[Autowired instances] handlers map
 * - findHandler: exact→parents→ifaces→Throwable key; StopFlow swallowed; miss→no-op
 * - NotFoundInterface→default shape; entry RequestFailed
 *
 * @see \Switon\Http\ExceptionDispatcherInterface
 * @see \Switon\Http\Event\RequestFailed
 */
class ExceptionDispatcher implements ExceptionDispatcherInterface
{
    /**
     * Exception class to handler map.
     *
     * @var array<string, ExceptionHandlerInterface>
     */
    #[Autowired(instances: true)] protected array $handlers = [
        NotAuthenticatedException::class => UnauthorizedHandler::class,
        UnauthorizedException::class => UnauthorizedHandler::class,
        ForbiddenException::class => ForbiddenHandler::class,
        NotFoundInterface::class => DefaultExceptionHandler::class,
        Throwable::class => DefaultExceptionHandler::class,
    ];

    /**
     * Dispatch one exception to the best matching handler.
     *
     * StopFlow from the handler is swallowed to preserve redirect/short-circuit flow.
     */
    public function dispatch(Throwable $exception): void
    {
        $handler = $this->findHandler($exception);
        if ($handler !== null) {
            try {
                // Handle exception (may set response, redirect, etc.)
                $handler->handle($exception);
            } catch (StopFlow) {
                // Handler set redirect, suppress StopFlow
                //no-op
            }
        }
    }

    /**
     * Find the most specific handler for one exception.
     */
    protected function findHandler(Throwable $exception): ?ExceptionHandlerInterface
    {
        $exceptionClass = $exception::class;

        // Check exact match first
        if (isset($this->handlers[$exceptionClass])) {
            return $this->handlers[$exceptionClass];
        }

        // Check parent classes (support inheritance)
        $class = $exceptionClass;
        while ($class !== false) {
            $parent = get_parent_class($class);
            if ($parent !== false && isset($this->handlers[$parent])) {
                return $this->handlers[$parent];
            }
            $class = $parent;
        }

        // Check interfaces (skip Throwable — it's the final fallback)
        $interfaces = class_implements($exceptionClass);
        foreach ($interfaces as $interface) {
            if ($interface !== Throwable::class && isset($this->handlers[$interface])) {
                return $this->handlers[$interface];
            }
        }

        // Throwable as final fallback
        return $this->handlers[Throwable::class] ?? null;
    }
}
