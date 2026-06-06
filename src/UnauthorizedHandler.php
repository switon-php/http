<?php

declare(strict_types=1);

namespace Switon\Http;

use Switon\Core\Attribute\Autowired;
use Switon\Http\Exception\UnauthorizedException;
use Switon\Principal\Exception\NotAuthenticatedException;
use Switon\Routing\RouterInterface;
use Throwable;

use function urlencode;

/**
 * Handles authentication failures in the HTTP pipeline.
 *
 * Road-signs:
 * - guest html → redirect /login
 * - redirect from request path / redirect
 * - json request → DefaultExceptionHandler (401)
 * - handles UnauthorizedException/NotAuthenticatedException
 *
 * @see \Switon\Http\ExceptionHandlerInterface
 * @see \Switon\Http\Exception\UnauthorizedException
 * @see \Switon\Principal\Exception\NotAuthenticatedException
 * @see \Switon\Http\DefaultExceptionHandler
 */
class UnauthorizedHandler implements ExceptionHandlerInterface
{
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected RouterInterface $router;

    public function handle(Throwable $throwable): bool
    {
        if (!$throwable instanceof UnauthorizedException && !$throwable instanceof NotAuthenticatedException) {
            return false;
        }

        // For guest users and non-JSON requests, redirect to login with HTTP 302
        if (!$this->request->wantsJson()) {
            $uri = $this->request->path();

            $redirect = $this->request->get('redirect', $uri);
            $loginPath = '/login';
            if ($redirect !== '/') {
                $loginPath .= '?redirect=' . urlencode($redirect);
            }
            $this->response->redirect($loginPath);
            return true;
        }

        // For JSON requests, let DefaultExceptionHandler handle it (401)
        return false;
    }
}
