<?php

declare(strict_types=1);

namespace Switon\Http\Filter;

use Switon\Core\Attribute\Autowired;
use Switon\Core\LocaleInterface;
use Switon\Eventing\Attribute\EventListener;
use Switon\Http\CookiesInterface;
use Switon\Http\Event\RequestBegin;
use Switon\Http\RequestInterface;

use function array_column;
use function array_key_exists;
use function explode;
use function is_string;
use function preg_match;
use function preg_split;
use function str_contains;
use function str_replace;
use function strtolower;
use function trim;
use function usort;

/**
 * Detects request locale (query, cookie, Accept-Language) and sets request-local locale.
 *
 * Only registered when {@see LocaleInterface} is bound (e.g. by switon/i18n).
 * Detection order: query parameter → cookie → Accept-Language header → default.
 *
 * @see \Switon\Core\LocaleInterface
 * @see \Switon\Http\Event\RequestBegin
 */
class RequestLocaleFilter
{
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected LocaleInterface $locale;
    #[Autowired] protected CookiesInterface $cookies;

    #[Autowired] protected string $query = 'lang';
    #[Autowired] protected string $cookie = 'locale';
    #[Autowired] protected string $header = 'accept-language';
    /** @var list<string> Supported locale codes used for request-side negotiation. */
    #[Autowired] protected array $supported = [];
    /** @var array<string, string> Locale aliases such as zh => zh-cn. */
    #[Autowired] protected array $aliases = [];

    #[EventListener] public function onBegin(RequestBegin $event): void
    {
        if (($locale = $this->request->query($this->query)) !== null) {
            $resolved = $this->resolvePreferredLocale($locale);
            if ($resolved !== null) {
                $this->locale->set($resolved);
                return;
            }
        }
        if (($locale = $this->cookies->get($this->cookie)) !== null) {
            $resolved = $this->resolvePreferredLocale($locale);
            if ($resolved !== null) {
                $this->locale->set($resolved);
                return;
            }
        }
        if (($acceptLanguage = $this->request->header($this->header)) !== null) {
            $locale = $this->parseAcceptLanguage($acceptLanguage);
            if ($locale !== null) {
                $this->locale->set($locale);
            }
        }
    }

    protected function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));
        $locale = str_replace('_', '-', $locale);
        if (str_contains($locale, '-')) {
            [$language, $region] = explode('-', $locale, 2);
            return $language . '-' . strtolower($region);
        }
        return $locale;
    }

    protected function resolvePreferredLocale(string $locale): ?string
    {
        $normalized = $this->normalizeLocale($locale);
        if ($normalized === '') {
            return null;
        }

        $normalized = $this->applyAlias($normalized);

        if ($this->supported === []) {
            return $normalized;
        }

        return $this->matchSupported([$normalized, $this->getLanguageOnly($normalized)]);
    }

    protected function parseAcceptLanguage(string $acceptLanguage): ?string
    {
        foreach ($this->extractAcceptLanguageCandidates($acceptLanguage) as $candidate) {
            $resolved = $this->resolvePreferredLocale($candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function extractAcceptLanguageCandidates(string $acceptLanguage): array
    {
        $candidates = [];

        foreach (preg_split('/\s*,\s*/', trim($acceptLanguage)) ?: [] as $index => $part) {
            if ($part === '') {
                continue;
            }

            $segments = explode(';', $part);
            $locale = trim($segments[0]);
            if (!preg_match('/^[a-z]{2,3}(?:[-_][a-z0-9]{2,8})?$/i', $locale)) {
                continue;
            }

            $quality = 1.0;
            foreach ($segments as $segment) {
                $segment = trim($segment);
                if (preg_match('/^q=([0-9.]+)$/i', $segment, $matches)) {
                    $quality = (float)$matches[1];
                    break;
                }
            }

            if ($quality <= 0) {
                continue;
            }

            $candidates[] = [
                'locale' => $locale,
                'quality' => $quality,
                'index' => $index,
            ];
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['quality'] === $b['quality']) {
                return $a['index'] <=> $b['index'];
            }

            return $a['quality'] < $b['quality'] ? 1 : -1;
        });

        return array_column($candidates, 'locale');
    }

    protected function applyAlias(string $locale): string
    {
        foreach ($this->aliases as $from => $to) {
            if ($this->normalizeLocale($from) === $locale) {
                return $this->normalizeLocale($to);
            }
        }

        return $locale;
    }

    protected function getLanguageOnly(string $locale): ?string
    {
        if (!str_contains($locale, '-')) {
            return null;
        }

        return explode('-', $locale, 2)[0];
    }

    /**
     * @param list<?string> $candidates
     */
    protected function matchSupported(array $candidates): ?string
    {
        $supported = [];
        foreach ($this->supported as $locale) {
            if (is_string($locale) && $locale !== '') {
                $supported[$this->normalizeLocale($locale)] = true;
            }
        }

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $candidate = $this->applyAlias($this->normalizeLocale($candidate));
            if (array_key_exists($candidate, $supported)) {
                return $candidate;
            }
        }

        return null;
    }
}
