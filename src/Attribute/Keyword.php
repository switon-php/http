<?php

declare(strict_types=1);

namespace Switon\Http\Attribute;

use Attribute;
use ReflectionParameter;
use Switon\Core\Attribute\Autowired;
use Switon\Http\Criteria;
use Switon\Http\CriteriaAttributeInterface;
use Switon\Http\KeywordTransformerInterface;
use Switon\Http\RequestInterface;

use function array_key_exists;
use function trim;

/**
 * Declares keyword search mapping for one action method.
 *
 * Road-signs:
 * - method annotation
 * - CriteriaResolver
 * - keyword param
 * - mapped filter key
 *
 * @see \Switon\Http\CriteriaResolver::resolve()
 * @see \Switon\Http\Criteria
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Keyword implements CriteriaAttributeInterface
{
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected KeywordTransformerInterface $keywordTransformer;

    /**
     * @param list<string> $fields
     */
    public function __construct(
        public readonly array  $fields,
        public readonly string $param = 'keyword',
        public readonly string $operator = '*=',
    ) {
    }

    public function apply(Criteria $criteria, ReflectionParameter $parameter): void
    {
        $rawKeyword = trim((string)$this->request->get($this->param, ''));
        if ($rawKeyword === '' || $this->fields === []) {
            return;
        }

        $keywordFilter = $this->keywordTransformer->transform($this->fields, $rawKeyword, $this->operator);
        if ($keywordFilter !== '' && !array_key_exists($keywordFilter, $criteria->filters)) {
            $criteria->filters[$keywordFilter] = $rawKeyword;
        }
    }
}
