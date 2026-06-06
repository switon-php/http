<?php

declare(strict_types=1);

namespace Switon\Http\Server\Adapter\Native;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Http\Event\BodySending;
use Switon\Http\Event\BodySent;
use Switon\Http\Event\HeadersSending;
use Switon\Http\Event\HeadersSent;
use Switon\Http\Exception\HeadersAlreadySentException;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Routing\RouterInterface;

use function readfile;
use function strlen;

/**
 * Sends HTTP response headers and body through native PHP output APIs.
 *
 * Use as the output backend for FPM and built-in PHP server adapters.
 *
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\Event\HeadersSending
 * @see \Switon\Http\Event\HeadersSent
 * @see \Switon\Http\Event\BodySending
 * @see \Switon\Http\Event\BodySent
 */
class Sender implements SenderInterface
{
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected HeadersInterface $headers;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected RouterInterface $router;

    public function sendHeaders(): void
    {
        if ($this->headers->headersSent($file, $line)) {
            throw HeadersAlreadySentException::at($file, $line);
        }

        $this->eventDispatcher->dispatch(new HeadersSending($this->response));

        $this->headers->header('HTTP/1.1 ' . $this->response->getStatus());

        foreach ($this->response->getHeaders() as $header => $value) {
            if ($value !== null) {
                $this->headers->header($header . ': ' . $value);
            } else {
                $this->headers->header($header);
            }
        }

        $prefix = $this->router->getPrefix();
        foreach ($this->response->getCookies() as $cookie) {
            $path = $cookie['path'] ?? '';
            $this->headers->setcookie(
                $cookie['name'],
                $cookie['value'],
                $cookie['expire'],
                $path === '' ? '' : ($prefix . $path),
                $cookie['domain'] ?? '',
                $cookie['secure'],
                $cookie['httponly']
            );
        }

        $this->eventDispatcher->dispatch(new HeadersSent($this->response));
    }

    public function sendBody(): void
    {
        $content = $this->response->getContent() ?? '';
        if ($this->response->getStatusCode() === 304) {
            //no-op
        } elseif ($this->request->isVerb('HEAD')) {
            $this->headers->header('Content-Length: ' . strlen($content));
        } elseif ($file = $this->response->getFile()) {
            readfile($file);
        } else {
            $event = new BodySending($this->response, $content);
            $this->eventDispatcher->dispatch($event);

            // Use potentially modified content from event
            $content = $event->content;

            echo $content;

            $this->eventDispatcher->dispatch(new BodySent($this->response, $content, true));
        }
    }
}
