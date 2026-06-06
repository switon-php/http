<?php

declare(strict_types=1);

namespace Switon\Http;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Switon\Core\Naming;
use Switon\Http\Exception\ActionNotFoundException;
use Switon\Http\Exception\ControllerNotFoundException;
use Switon\Http\Exception\InvalidControllerException;
use Switon\Routing\Attribute\MappingInterface;
use Switon\Routing\Attribute\RequestMapping;

use function basename;
use function is_array;
use function preg_match;
use function str_ends_with;
use function str_replace;

/**
 * Resolves action metadata and URL paths from controller classes.
 *
 * Use when route generation or introspection needs action lists and <code>Controller::Action</code> paths.
 *
 * Road-signs:
 * - action `#[*Mapping]`
 * - controller <code>#[RequestMapping]</code>
 * - url path Naming::snake
 * - fail ControllerNotFound/InvalidController/ActionNotFound
 * - Action suffix normalization
 *
 * @see \Switon\Http\ControllerMetadataInterface
 * @see \Switon\Routing\Attribute\RequestMapping
 * @see \Switon\Routing\Attribute\MappingInterface
 * @see \Switon\Core\Naming
 */
class ControllerMetadata implements ControllerMetadataInterface
{
    public function getActions(string $controller): array
    {
        try {
            $rClass = new ReflectionClass($controller);
        } catch (ReflectionException $e) {
            ControllerNotFoundException::raise('Controller class "{controller}" not found', ['controller' => $controller], 0, $e);
        }

        if (!$this->hasRequestMapping($rClass)) {
            InvalidControllerException::raise('Controller "{controller}" is missing #[RequestMapping] attribute', ['controller' => $controller]);
        }

        $actions = [];
        foreach ($rClass->getMethods(ReflectionMethod::IS_PUBLIC) as $rMethod) {
            if (!$this->hasActionMapping($rMethod)) {
                continue;
            }
            $actions[] = $rMethod->getName();
        }

        return $actions;
    }

    public function getPath(string $controller, string $action): string
    {
        try {
            $rClass = new ReflectionClass($controller);
        } catch (ReflectionException $e) {
            ControllerNotFoundException::raise('Controller class "{controller}" not found', ['controller' => $controller], 0, $e);
        }

        if (!$this->hasRequestMapping($rClass)) {
            InvalidControllerException::raise('Controller "{controller}" is missing #[RequestMapping] attribute', ['controller' => $controller]);
        }

        $requestMapping = $rClass->getAttributes(RequestMapping::class)[0]->newInstance();
        $actionName = basename($action, 'Action');
        $fullMethodName = str_ends_with($action, 'Action') ? $action : ($action . 'Action');

        if (!$rClass->hasMethod($fullMethodName)) {
            if (!$rClass->hasMethod($actionName)) {
                ActionNotFoundException::raise(
                    'Action method "{action}" not found in controller "{controller}"',
                    ['controller' => $controller, 'action' => $fullMethodName]
                );
            }
            $fullMethodName = $actionName;
        }

        $action = Naming::snake($actionName);
        $path = $requestMapping->getPath();
        if (is_array($path)) {
            $path = $path[0] ?? null;
        }
        if (is_string($path) && $path !== '') {
            return $this->buildPathFromPrefix($path, $action);
        }

        $controllerPath = str_replace('\\', '/', $controller);

        if (preg_match('#Areas/([^/]+)/Controller/(.*)Controller$#', $controllerPath, $match)) {
            $area = Naming::snake($match[1]);
            $controller = Naming::snake($match[2]);

            if ($action === 'index') {
                if ($controller === 'index') {
                    return $area === 'index' ? '/' : "/$area";
                } else {
                    return "/$area/$controller";
                }
            } else {
                return "/$area/$controller/$action";
            }
        } elseif (preg_match('#/Controller/(.*)Controller#', $controllerPath, $match)) {
            $controller = Naming::snake($match[1]);

            if ($action === 'index') {
                return $controller === 'index' ? '/' : "/$controller";
            } else {
                return "/$controller/$action";
            }
        } else {
            InvalidControllerException::raise('Invalid controller path format: "{path}"', ['path' => $controllerPath]);
        }
    }

    protected function buildPathFromPrefix(string $prefix, string $action): string
    {
        $prefix = rtrim($prefix, '/');

        if ($action === 'index') {
            return $prefix === '' ? '/' : $prefix;
        }

        return $prefix === '' ? '/' . $action : $prefix . '/' . $action;
    }

    /** @param ReflectionClass<object> $rClass */
    protected function hasRequestMapping(ReflectionClass $rClass): bool
    {
        return $rClass->getAttributes(RequestMapping::class) !== [];
    }

    protected function hasActionMapping(ReflectionMethod $rMethod): bool
    {
        return $rMethod->getAttributes(MappingInterface::class, ReflectionAttribute::IS_INSTANCEOF) !== [];
    }
}
