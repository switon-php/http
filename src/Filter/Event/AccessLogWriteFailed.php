<?php

declare(strict_types=1);

namespace Switon\Http\Filter\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\Severity;
use Throwable;

/**
 * Event emitted when access log writing fails.
 *
 * Log category: <code>switon.http.filters.access.log.write.failed</code>
 *
 * @see \Switon\Http\Filter\AccessLogFilter::onEnd()
 * @see \Switon\Core\FilesystemInterface
 */
#[EventLevel(Severity::ERROR)]
class AccessLogWriteFailed
{
    public function __construct(
        public string  $file,
        public string  $exceptionMessage,
        public string  $exceptionClass,
        public ?string $exceptionFile = null,
        public ?int    $exceptionLine = null,
    ) {
    }

    public static function from(string $file, Throwable $exception): self
    {
        return new self(
            $file,
            $exception->getMessage(),
            $exception::class,
            $exception->getFile(),
            $exception->getLine(),
        );
    }
}
