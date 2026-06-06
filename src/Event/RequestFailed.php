<?php

declare(strict_types=1);

namespace Switon\Http\Event;

use JsonSerializable;
use Psr\Log\LoggerInterface;
use Switon\Core\Categorized;
use Switon\Core\Exception;
use Switon\Eventing\EventLogInterface;
use Switon\Eventing\Severity;
use Throwable;

/**
 * Event emitted when request handling fails with an exception.
 *
 * Road-signs:
 * - emitted after ExceptionDispatcher
 * - carries Throwable
 * - statusCode from Core\Exception
 * - 5xx error vs 4xx debug
 * - merges Exception::getContext()
 *
 * @see \Switon\Http\ExceptionDispatcherInterface
 * @see \Switon\Http\ExceptionDispatcher
 * @see \Switon\Http\ExceptionHandlerInterface
 * @see \Switon\Http\RequestHandlerInterface
 * @see \Switon\Http\RequestHandler::handle()
 * @see \Switon\Http\DefaultExceptionHandler
 * @see \Switon\Core\Exception
 */
class RequestFailed implements JsonSerializable, EventLogInterface
{
    public function __construct(
        public Throwable $exception
    ) {
    }

    public function log(object $event, LoggerInterface $logger): void
    {
        if (!$event instanceof self) {
            return;
        }

        $exception = $event->exception;
        $statusCode = $exception instanceof Exception ? $exception->getStatusCode() : 500;

        // Determine log level based on status code
        if ($statusCode >= 500 && $statusCode <= 599) {
            // 5xx errors - server errors (serious)
            $level = Severity::ERROR;
        } else {
            // 4xx errors and others - client errors (normal business flow, like 401 Unauthorized)
            $level = Severity::DEBUG;
        }

        $context = [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        if ($exception instanceof Exception) {
            $context = array_merge($context, $exception->getContext());
        }

        $message = Categorized::of('switon.http.request.failed', 'Request failed: {exception}');
        $logger->log($level->value, $message, $context);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'exception' => $this->exception::class,
            'message' => $this->exception->getMessage(),
            'code' => $this->exception->getCode(),
            'file' => $this->exception->getFile(),
            'line' => $this->exception->getLine(),
            'trace' => $this->exception->getTraceAsString(),
        ];
    }
}
