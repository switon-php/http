<?php

declare(strict_types=1);

namespace Switon\Http;

/**
 * Transforms keyword mapping before CriteriaResolver builds filter key.
 *
 * Guidance: Keep transformation deterministic and field-whitelist friendly.
 *
 * @see \Switon\Http\KeywordTransformer
 * @see \Switon\Http\CriteriaResolver::resolve()
 * @see \Switon\Http\Attribute\Keyword
 */
interface KeywordTransformerInterface
{
    /**
     * @param list<string> $fields
     */
    public function transform(array $fields, string $keyword, string $operator): string;
}
