<?php

declare(strict_types=1);

namespace Switon\Http;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Http\Event\ChunkWriting;
use Switon\Http\Event\ChunkWritten;
use Switon\Routing\RouterInterface;
use Throwable;

use function date;
use function ob_flush;
use function preg_replace;
use function sprintf;
use function strlen;

/**
 * Base class for HTTP server adapters.
 *
 * Guidance: Subclasses own the transport loop; reuse this class for shared write and formatting behavior.
 *
 * Road-signs:
 * - subclasses call RequestHandler for each request
 * - write() dispatches ChunkWriting then ChunkWritten
 * - formatException() builds one loggable stack string
 *
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\RequestHandlerInterface
 * @see \Switon\Http\Event\ChunkWriting
 * @see \Switon\Http\Event\ChunkWritten
 */
abstract class AbstractServer implements ServerInterface
{
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected RouterInterface $router;
    #[Autowired] protected RequestHandlerInterface $requestHandler;
    #[Autowired] protected ServerOptions $serverOptions;

    public function __construct(protected AppInterface $app)
    {
    }

    /**
     * Write one chunk after chunk events have adjusted it.
     */
    public function write(string $chunk): bool
    {
        $event = new ChunkWriting($this->response, $chunk);
        $this->eventDispatcher->dispatch($event);

        // Use potentially modified chunk from event
        $chunk = $event->chunk;

        if ($chunk !== '') {
            echo sprintf('%X', strlen($chunk)) . "\r\n" . $chunk . "\r\n";
        } else {
            echo "0\r\n\r\n";
        }

        ob_flush();

        $this->eventDispatcher->dispatch(new ChunkWritten($this->response, $chunk, true));

        return true;
    }

    /**
     * Format one throwable for adapter-level logs.
     */
    protected function formatException(Throwable $throwable): string
    {
        $str = date('c') . ' ' . $throwable::class . ': ' . $throwable->getMessage() . PHP_EOL;
        $str .= '    at ' . $throwable->getFile() . ':' . $throwable->getLine() . PHP_EOL;
        $str .= preg_replace('/#\d+\s/', '    at ', $throwable->getTraceAsString());

        return $str . PHP_EOL;
    }
}
