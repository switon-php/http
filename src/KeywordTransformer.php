<?php

declare(strict_types=1);

namespace Switon\Http;

use function array_filter;
use function array_values;
use function implode;
use function str_contains;
use function strtolower;

/**
 * Default keyword transformer used by CriteriaResolver.
 *
 * Strategy:
 * - when keyword contains '@' and email-like fields exist, narrow to email-like fields
 * - otherwise keep the declared field set unchanged
 *
 * @see \Switon\Http\KeywordTransformerInterface
 * @see \Switon\Http\CriteriaResolver
 */
class KeywordTransformer implements KeywordTransformerInterface
{
    public function transform(array $fields, string $keyword, string $operator): string
    {
        if (str_contains($keyword, '@')) {
            $emailFields = array_values(array_filter(
                $fields,
                static fn (string $field): bool => str_contains(strtolower($field), 'email')
            ));
            if ($emailFields !== []) {
                return implode(',', $emailFields) . $operator;
            }
        }

        return implode(',', $fields) . $operator;
    }
}
