<?php

declare(strict_types=1);

namespace Switon\Http;

use ReflectionParameter;
use Switon\Binding\InputBinderInterface;
use Switon\Binding\ValueResolverInterface;
use Switon\Core\Attribute\Autowired;

/**
 * Resolves typed input parameters marked with request-source attributes from HTTP input payload.
 *
 * Use when object parameters should be populated and validated from request data fields.
 *
 * @see \Switon\Http\Attribute\RequestData
 * @see \Switon\Http\Attribute\RequestQuery
 * @see \Switon\Http\Attribute\RequestBody
 * @see \Switon\Binding\InputBinderInterface
 * @see \Switon\Binding\ValueResolverInterface
 * @see \Switon\Binding\ObjectResolver::resolve()
 */
class RequestDataResolver implements ValueResolverInterface
{
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected InputBinderInterface $inputBinder;

    /**
     * @param class-string $type
     */
    public function resolve(ReflectionParameter $parameter, string $type): object
    {
        return $this->inputBinder->bind($type, $this->request->all());
    }
}
