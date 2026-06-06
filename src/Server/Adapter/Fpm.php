<?php

declare(strict_types=1);

namespace Switon\Http\Server\Adapter;

use Switon\Core\Attribute\Autowired;
use Switon\Http\AbstractServer;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Server\Adapter\Native\SenderInterface;
use Switon\Http\Server\Event\ServerReady;

use function file_get_contents;

/**
 * Runs one HTTP request with PHP-FPM globals and native sender output.
 *
 * Guidance: Use for one-request-per-process SAPIs; transport setup stays here, request pipeline stays in RequestHandler.
 *
 * Road-signs:
 * - prepareGlobals emits RequestReceived
 * - start emits ServerReady
 * - RequestHandler handles the request
 * - native sender writes headers and body
 *
 * @see \Switon\Http\ServerInterface
 * @see \Switon\Http\AbstractServer
 * @see \Switon\Http\Event\RequestReceived
 * @see \Switon\Http\Server\Event\ServerReady
 * @see \Switon\Http\RequestHandlerInterface
 * @see \Switon\Http\Server\Adapter\Native\SenderInterface
 */
class Fpm extends AbstractServer
{
    #[Autowired] protected SenderInterface $sender;

    protected function prepareGlobals(): void
    {
        $raw = file_get_contents('php://input');
        $rawBody = $raw === false ? null : $raw;
        $this->eventDispatcher->dispatch(new RequestReceived($_GET, $_POST, $_SERVER, $rawBody, $_COOKIE, $_FILES));
    }

    public function start(): void
    {
        $this->prepareGlobals();

        $this->eventDispatcher->dispatch(new ServerReady(null, $this->serverOptions->host, $this->serverOptions->port));

        $this->requestHandler->handle();
    }

    public function sendHeaders(): void
    {
        $this->sender->sendHeaders();
    }

    public function sendBody(): void
    {
        $this->sender->sendBody();
    }
}
