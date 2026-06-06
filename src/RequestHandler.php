<?php

declare(strict_types=1);

namespace Switon\Http;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionAttribute;
use ReflectionMethod;
use Switon\Binding\ArgumentsBinderInterface;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Json;
use Switon\Core\StopFlow;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Http\Event\ControllerResolved;
use Switon\Http\Event\RequestAdjust;
use Switon\Http\Event\RequestAuthenticated;
use Switon\Http\Event\RequestAuthenticating;
use Switon\Http\Event\RequestAuthorized;
use Switon\Http\Event\RequestAuthorizing;
use Switon\Http\Event\RequestBegin;
use Switon\Http\Event\RequestEnd;
use Switon\Http\Event\RequestFailed;
use Switon\Http\Event\RequestInvoked;
use Switon\Http\Event\RequestInvoking;
use Switon\Http\Event\RequestReady;
use Switon\Http\Event\RequestRendered;
use Switon\Http\Event\RequestRendering;
use Switon\Http\Event\RequestRouted;
use Switon\Http\Event\RequestRouting;
use Switon\Http\Event\RequestValidated;
use Switon\Http\Event\RequestValidating;
use Switon\Http\Event\ResponseAdjust;
use Switon\Http\Event\ResponseStringify;
use Switon\Http\Exception\ActionNotFoundException;
use Switon\Http\Exception\ControllerNotFoundException;
use Switon\Http\Exception\InvalidControllerException;
use Switon\Invoking\InvokerInterface;
use Switon\Routing\Attribute\Viewable;
use Switon\Routing\Attribute\ViewGetMapping;
use Switon\Routing\Attribute\ViewMapping;
use Switon\Routing\Exception\MethodNotAllowedException;
use Switon\Routing\Exception\NotFoundRouteException;
use Switon\Routing\RouterInterface;
use Throwable;

use function array_merge;
use function class_exists;
use function explode;
use function is_array;
use function method_exists;

/**
 * HTTP coordinator: route, auth(z), validate, invoke, render; stage hooks = Request* events.
 *
 * Guidance: Keep ResponseAdjust, ResponseStringify, and RequestEnd listeners no-throw; failures there are treated as app/server boundary issues.
 * Guidance: Route variables override POST and query data when merged into the request context.
 *
 * Road-signs:
 * - boot: listenerProvider registers each filter and transformer
 * - RequestAdjust → parseBody → RequestBegin → Router+Invoker; errors→RequestFailed→ExceptionDispatcher
 * - route miss -> alternate verb probe -> 405
 * - ResponseAdjust (all outcomes) → if content array: ResponseStringify + JSON encode → send (string skips encode)
 * - RequestHandler normalizes main pipeline failures; response-finish listener failures are outside that contract
 *
 * @see \Switon\Http\RequestHandlerInterface
 * @see \Switon\Routing\RouterInterface::match()
 * @see \Switon\Invoking\InvokerInterface::invoke()
 * @see \Switon\Http\Event\RequestRouting
 * @see \Switon\Http\Event\RequestRouted
 * @see \Switon\Http\Event\RequestInvoking
 * @see \Switon\Http\Event\RequestInvoked
 * @see \Switon\Http\Event\RequestAdjust
 * @see \Switon\Http\Event\RequestBegin
 * @see \Switon\Http\Event\ResponseAdjust
 * @see \Switon\Http\ExceptionDispatcherInterface
 * @see \Switon\Http\Event\RequestFailed
 * @see \Switon\Http\Event\RequestEnd
 * @see \Switon\Http\Event\ResponseStringify
 * @see \Switon\Core\ContextAware
 * @see \Switon\Core\ContextManagerInterface
 * @see \Switon\Context\ContextManager
 * @see \Switon\Invocation\Attribute\InterceptorInterface
 * @see \Switon\Orm\Attribute\Transactional
 * @see \Switon\Db\TransactionManagerInterface
 * @see \Switon\Authorizing\AuthorizationInterface::authorize()
 */
class RequestHandler implements RequestHandlerInterface
{
    #[Autowired] protected AppInterface $app;
    #[Autowired] protected ContainerInterface $container;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected ListenerProviderInterface $listenerProvider;
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;
    #[Autowired] protected RouterInterface $router;
    #[Autowired] protected ServerInterface $httpServer;
    #[Autowired] protected ArgumentsBinderInterface $argumentsBinder;
    #[Autowired] protected InvokerInterface $invoker;
    #[Autowired] protected ExceptionDispatcherInterface $exceptionDispatcher;

    /** @var array<string, class-string> HTTP filter classes registered as lifecycle listeners during boot. */
    #[Autowired(instances: true)] protected array $filters
        = [
            'requestId' => Filter\RequestIdFilter::class,
        ];

    /** @var array<string, class-string> HTTP transformer classes registered as lifecycle listeners during boot. */
    #[Autowired(instances: true)] protected array $transformers
        = [
            'normalizeActionReturn' => Transformer\NormalizeActionReturnTransformer::class,
        ];

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        foreach ($this->filters as $filter) {
            $this->listenerProvider->register($filter);
        }

        foreach ($this->transformers as $transformer) {
            $this->listenerProvider->register($transformer);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function handle(): void
    {
        try {
            $this->eventDispatcher->dispatch(new RequestAdjust($this->request));
            $this->request->parseBody();

            $this->eventDispatcher->dispatch(new RequestBegin($this->request));

            $this->eventDispatcher->dispatch(new RequestAuthenticating($this->request));
            $this->eventDispatcher->dispatch(new RequestAuthenticated($this->request));

            $this->eventDispatcher->dispatch(new RequestRouting($this->router, $this->request));

            $uri = $this->request->path();
            $verb = $this->request->verb();

            // Match route (Router handles route matching, parameter collection, and routing events)
            try {
                $matcher = $this->router->match($uri, $verb);
            } catch (NotFoundRouteException $exception) {
                if ($this->probeMethodNotAllowed($uri, $verb)) {
                    $context = ['method' => $verb, 'uri' => $uri];
                    MethodNotAllowedException::raise('Method "{method}" not allowed for {uri}.', $context);
                }

                throw $exception;
            }

            $context = $this->request->getContext();
            $context->matcher = $matcher;

            // Merge route variables into _REQUEST (highest priority: route > POST > Query)
            if ($variables = $matcher->getVariables()) {
                $context->_REQUEST = array_merge($context->_REQUEST, $variables);
            }

            $handler = $matcher->getHandler();

            // Resolve handler string to controller and action
            $parts = explode('::', $handler, 2);
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                InvalidControllerException::raise('Invalid handler format: {handler}', ['handler' => $handler]);
            }
            [$controller, $action] = $parts;

            // Validate controller class exists
            if (!class_exists($controller)) {
                ControllerNotFoundException::raise('Controller not found: {controller}', ['controller' => $controller]);
            }

            // Validate action method exists
            if (!method_exists($controller, $action)) {
                ActionNotFoundException::raise('Action not found: {controller}::{action}', ['controller' => $controller, 'action' => $action]);
            }

            $this->eventDispatcher->dispatch(new RequestRouted($this->router, $matcher, $this->request));

            $method = new ReflectionMethod($controller, $action);

            $this->eventDispatcher->dispatch(new RequestAuthorizing($method));
            $this->eventDispatcher->dispatch(new RequestAuthorized($method));

            $this->eventDispatcher->dispatch(new RequestValidating($method));
            $this->eventDispatcher->dispatch(new RequestValidated($method));

            $this->eventDispatcher->dispatch(new RequestReady($method));

            $this->eventDispatcher->dispatch(new RequestInvoking($method));
            $return = $this->invoke($method);
            $this->eventDispatcher->dispatch(new RequestInvoked($method, $return));
        } catch (StopFlow) {
            //no-op
        } catch (Throwable $exception) {
            // Dispatch exception to appropriate handler
            $this->exceptionDispatcher->dispatch($exception);
            // Always dispatch RequestFailed event for logging, monitoring, etc.
            $this->eventDispatcher->dispatch(new RequestFailed($exception));
        }

        $this->eventDispatcher->dispatch(new ResponseAdjust($this->request, $this->response));

        $content = $this->response->getContent();
        if (is_array($content)) {
            $this->eventDispatcher->dispatch(new ResponseStringify($this->response));
            // Re-fetch: ResponseStringify listeners may replace or convert the body.
            $content = $this->response->getContent();
            if (is_array($content)) {
                $this->response->setContent(Json::stringify($content));
            }
        }

        if ($this->response->isChunked()) {
            $this->httpServer->write('');
        } else {
            $this->httpServer->sendHeaders();
            $this->httpServer->sendBody();
        }

        $this->eventDispatcher->dispatch(new RequestEnd($this->request, $this->response));
    }

    /**
     * Probes common form verbs by reusing router matching and swallowing route misses.
     *
     * Only `GET`, `POST`, `PUT`, `PATCH`, and `DELETE` are probed here.
     * `HEAD` and `OPTIONS` are intentionally excluded because they are not used
     * for the method-not-allowed fallback in this handler.
     */
    protected function probeMethodNotAllowed(string $uri, string $verb): bool
    {
        $verbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($verbs as $probeVerb) {
            if ($probeVerb === $verb) {
                continue;
            }
            try {
                $this->router->match($uri, $probeVerb);
                return true;
            } catch (NotFoundRouteException) {
                // Try the next common verb.
            }
        }

        return false;
    }

    protected function invoke(ReflectionMethod $rMethod): mixed
    {
        $controller = $this->container->get($rMethod->getDeclaringClass()->getName());
        $event = new ControllerResolved($controller, $rMethod);
        $this->eventDispatcher->dispatch($event);

        // Use controller from event (maybe replaced by listeners)
        $controller = $event->controller;

        if (!$this->hasViewable($rMethod)) {
            $arguments = $this->argumentsBinder->resolve($rMethod);
            return $this->invoker->invoke([$controller, $rMethod->name], $arguments);
        }

        $return = $this->shouldInvokeAction($rMethod)
            ? $this->invoker->invoke([$controller, $rMethod->name], $this->argumentsBinder->resolve($rMethod))
            : [];

        $alwaysRenderView = $this->hasViewMapping($rMethod);
        if ($this->request->isVerb('GET') && ($alwaysRenderView || !$this->request->wantsJson())) {
            $this->eventDispatcher->dispatch(new RequestRendering($rMethod, $return, $this->request, $this->response, $this->router->getPrefix()));
            $this->eventDispatcher->dispatch(new RequestRendered($rMethod, $this->request, $this->response));
            return null;
        }

        // Return action result (JSON)
        return $return;
    }

    protected function shouldInvokeAction(ReflectionMethod $rMethod): bool
    {
        $isGetRequest = $this->request->isVerb('GET');

        if (!$isGetRequest) {
            // Non-GET request: always invoke action
            return true;
        }

        if ($this->hasViewMapping($rMethod)) {
            return true;
        }

        if ($this->hasViewGetMapping($rMethod)) {
            // ViewGetMapping: invoke action when the client prefers JSON; otherwise render the view (no invoke)
            return $this->request->wantsJson();
        }

        return false;
    }

    /** Whether the method has a Viewable attribute (ViewMapping, ViewGetMapping, etc.). */
    protected function hasViewable(ReflectionMethod $rMethod): bool
    {
        return $rMethod->getAttributes(Viewable::class, ReflectionAttribute::IS_INSTANCEOF) !== [];
    }

    /** Whether the method has #[ViewMapping] (always render view for GET). */
    protected function hasViewMapping(ReflectionMethod $rMethod): bool
    {
        return $rMethod->getAttributes(ViewMapping::class, ReflectionAttribute::IS_INSTANCEOF) !== [];
    }

    /** Whether the method has #[ViewGetMapping] (HTML GET when {@see RequestInterface::wantsJson()} is false). */
    protected function hasViewGetMapping(ReflectionMethod $rMethod): bool
    {
        return $rMethod->getAttributes(ViewGetMapping::class, ReflectionAttribute::IS_INSTANCEOF) !== [];
    }
}
