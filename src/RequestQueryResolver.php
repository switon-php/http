<?php

declare(strict_types=1);

namespace Switon\Http;

use ReflectionParameter;
use Switon\Binding\InputBinderInterface;
use Switon\Binding\ValueResolverInterface;
use Switon\Core\Attribute\Autowired;

/**
 * Resolves typed input parameters from query-string input only.
 *
 * Guidance: Bind only from query payload returned by request query API; do not merge other sources.
 *
 * @see \Switon\Http\Attribute\RequestQuery
 * @see \Switon\Binding\InputBinderInterface
 * @see \Switon\Binding\ValueResolverInterface
 */
class RequestQueryResolver implements ValueResolverInterface
{
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected InputBinderInterface $inputBinder;

    /**
     * @param class-string $type
     */
    public function resolve(ReflectionParameter $parameter, string $type): object
    {
        return $this->inputBinder->bind($type, $this->request->query());
    }
}
