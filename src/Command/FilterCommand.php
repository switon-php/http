<?php

declare(strict_types=1);

namespace Switon\Http\Command;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Switon\Command\Attribute\Hidden;
use Switon\Command\Attribute\Tool;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ConsoleInterface;
use Switon\Core\FilterMatcherInterface;
use Switon\Core\Json;
use Switon\Eventing\Attribute\EventListener;
use Switon\Http\FilterDiscoveryInterface;
use Switon\Http\RequestHandlerInterface;

use function class_exists;
use function is_array;
use function is_string;

/**
 * AI-facing filter inspection tools for HTTP component.
 *
 * Road-signs:
 * - exposes filter:list for AI/tooling use
 * - discover() provides all known filter classes
 * - RequestHandler contributes the enabled subset
 *
 * @see \Switon\Http\FilterDiscoveryInterface
 * @see \Switon\Http\RequestHandlerInterface
 * @see \Switon\Command\Attribute\Tool
 */
#[Hidden]
class FilterCommand
{
    #[Autowired] protected ConsoleInterface $console;

    #[Autowired] protected FilterDiscoveryInterface $filterDiscovery;

    #[Autowired] protected RequestHandlerInterface $requestHandler;

    #[Autowired] protected FilterMatcherInterface $filterMatcher;

    /**
     * List discovered HTTP filters for AI tooling.
     *
     * Filter matches class FQCN by substring or wildcards `*` `?`.
     * `--enabled` limits output to filter classes present in `RequestHandler::$filters`.
     *
     * @param string $filter Class filter (substring or wildcards * ?), optional.
     * @param bool $enabled When true, only return enabled filters.
     */
    #[Tool('filter:list [filter] [--enabled]. Returns JSON: [{class,file,description,events[],enabled}].')]
    public function listAction(string $filter = '', bool $enabled = false): int
    {
        $filters = $this->filterDiscovery->discover();
        $enabledMap = $this->getEnabledFilterMap();

        $result = [];
        foreach ($filters as $class) {
            if (!is_string($class) || $class === '' || !class_exists($class)) {
                continue;
            }

            $isEnabled = $enabledMap[$class] ?? false;
            if ($enabled && !$isEnabled) {
                continue;
            }

            $info = $this->buildFilterInfo($class, $isEnabled);

            if ($filter !== '' && !$this->matchesFilter($filter, $info)) {
                continue;
            }

            $result[] = $info;
        }

        $this->console->writeLn(Json::stringify($result));
        return 0;
    }

    /**
     * Build the enabled-filter lookup from RequestHandler configuration.
     *
     * @return array<class-string,bool>
     */
    protected function getEnabledFilterMap(): array
    {
        $map = [];

        $rClass = new ReflectionClass($this->requestHandler);
        if (!$rClass->hasProperty('filters')) {
            return $map;
        }

        $property = $rClass->getProperty('filters');
        $value = $property->getValue($this->requestHandler);

        if (!is_array($value)) {
            return $map;
        }

        foreach ($value as $filter) {
            if (is_string($filter) && $filter !== '' && class_exists($filter)) {
                $map[$filter] = true;
            } elseif (is_object($filter)) {
                $map[$filter::class] = true;
            }
        }

        return $map;
    }

    /**
     * Build one filter info payload.
     *
     * @param class-string $class
     *
     * @return array{class: class-string, file: string|null, description: string|null, events: list<string>, enabled: bool}
     */
    protected function buildFilterInfo(string $class, bool $enabled): array
    {
        $rClass = new ReflectionClass($class);

        $description = $this->extractFirstDocLine($rClass);

        $events = [];
        foreach ($rClass->getMethods(ReflectionMethod::IS_PUBLIC) as $rMethod) {
            $attributes = $rMethod->getAttributes(EventListener::class, ReflectionAttribute::IS_INSTANCEOF);
            if ($attributes === []) {
                continue;
            }

            $eventClass = '';
            if ($rMethod->getNumberOfParameters() > 0) {
                $param = $rMethod->getParameters()[0];
                $type = $param->getType();

                if ($type instanceof ReflectionNamedType) {
                    // Single named type (class or builtin like "object")
                    $name = $type->getName();
                    $eventClass = $name === 'object' ? '*' : $name;
                } elseif ($type instanceof ReflectionUnionType) {
                    // Union type (A|B) – join all names with "|"
                    $names = [];
                    foreach ($type->getTypes() as $inner) {
                        if ($inner instanceof ReflectionNamedType) {
                            $names[] = $inner->getName();
                        }
                    }
                    $eventClass = $names === [] ? '' : implode('|', $names);
                } elseif ($type instanceof ReflectionIntersectionType) {
                    // Intersection type (A&B) – join all names with "&"
                    $names = [];
                    foreach ($type->getTypes() as $inner) {
                        if ($inner instanceof ReflectionNamedType) {
                            $names[] = $inner->getName();
                        }
                    }
                    $eventClass = $names === [] ? '' : implode('&', $names);
                }
            }

            $signature = $eventClass !== ''
                ? $eventClass . '@' . $rMethod->getName()
                : $rMethod->getName();

            $events[] = $signature;
        }

        return [
            'class' => $class,
            'file' => $rClass->getFileName() ?: null,
            'description' => $description,
            'events' => $events,
            'enabled' => $enabled,
        ];
    }

    /**
     * Extract the first non-empty PHPDoc line from one class docblock.
     *
     * @param ReflectionClass<object> $rClass
     */
    protected function extractFirstDocLine(ReflectionClass $rClass): ?string
    {
        $doc = $rClass->getDocComment();
        if ($doc === false || $doc === '') {
            return null;
        }

        $lines = preg_split('/\R/', $doc);
        if (!is_array($lines)) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '/**' || $line === '*/' || $line === '') {
                continue;
            }

            // Strip leading "*" and whitespace
            if (str_starts_with($line, '*')) {
                $line = ltrim(substr($line, 1));
            }

            if ($line === '') {
                continue;
            }

            return $line;
        }

        return null;
    }

    /**
     * Match filter against filter info (class, file, events).
     *
     * @param array{class: class-string, file: string|null, description: string|null, events: list<string>, enabled: bool} $info
     */
    protected function matchesFilter(string $filter, array $info): bool
    {
        $subjects = [
            'class' => $info['class'] ?? '',
        ];

        if (($info['file'] ?? '') !== '') {
            $subjects['file'] = $info['file'];
        }

        if (isset($info['events']) && is_array($info['events'])) {
            $subjects['events'] = $info['events'];
        }

        return $this->filterMatcher->matchAny($filter, $subjects);
    }

}
