<?php

declare(strict_types=1);

namespace Switon\Http;

use Switon\Core\Attribute\Autowired;
use Switon\Http\Exception\ForbiddenException;
use Throwable;

/**
 * Handles forbidden access failures in the HTTP pipeline.
 *
 * Road-signs:
 * - 403 for authenticated users
 * - json accept → json body
 * - else text/plain body
 *
 * @see \Switon\Http\ExceptionHandlerInterface
 * @see \Switon\Http\Exception\ForbiddenException
 */
class ForbiddenHandler implements ExceptionHandlerInterface
{
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;

    public function handle(Throwable $throwable): bool
    {
        if (!$throwable instanceof ForbiddenException) {
            return false;
        }

        if ($this->request->wantsJson()) {
            $this->response->json(['code' => 403, 'msg' => 'Access denied to resource.'], 403);
        } else {
            $this->response->text('Access denied to resource.', 'text/plain', 403);
        }

        return true;
    }
}
