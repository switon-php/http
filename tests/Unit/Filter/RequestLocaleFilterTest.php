<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Filter;

use Switon\Core\LocaleInterface;
use Switon\Http\CookiesInterface;
use Switon\Http\Event\RequestBegin;
use Switon\Http\Filter\RequestLocaleFilter;
use Switon\Http\RequestInterface;
use Switon\Http\Tests\Fixtures\TestCookiesStub;
use Switon\Http\Tests\Fixtures\TestRequestStub;
use Switon\Http\Tests\TestCase;

final class RequestLocaleFilterTest extends TestCase
{
    private function makeRecordingLocale(): RecordingLocale
    {
        return new RecordingLocale();
    }

    /**
     * @param array{supported?: list<string>, aliases?: array<string, string>} $props
     */
    private function makeFilter(
        TestRequestStub $request,
        TestCookiesStub $cookies,
        RecordingLocale $locale,
        array           $props = [],
    ): RequestLocaleFilter {
        $this->container->set(RequestInterface::class, $request);
        $this->container->set(CookiesInterface::class, $cookies);
        $this->container->set(LocaleInterface::class, $locale);

        return $this->container->make(RequestLocaleFilter::class, $props);
    }

    public function testOnBeginSetsLocaleFromQueryParameter(): void
    {
        $request = new TestRequestStub();
        $request->setQueryParam('fr-CA');
        $cookies = new TestCookiesStub();
        $locale = $this->makeRecordingLocale();

        $filter = $this->makeFilter($request, $cookies, $locale);
        $filter->onBegin(new RequestBegin($request));

        $this->assertSame(1, $locale->setCount);
        $this->assertSame('fr-ca', $locale->setValue);
    }

    public function testOnBeginFallsBackToCookieWhenQueryDoesNotResolve(): void
    {
        $request = new TestRequestStub();
        $request->setQueryParam('');
        $cookies = new TestCookiesStub();
        $cookies->setCookie('locale', 'DE_de');
        $locale = $this->makeRecordingLocale();

        $filter = $this->makeFilter($request, $cookies, $locale);
        $filter->onBegin(new RequestBegin($request));

        $this->assertSame(1, $locale->setCount);
        $this->assertSame('de-de', $locale->setValue);
    }

    public function testOnBeginUsesAcceptLanguageWithQualityOrdering(): void
    {
        $request = new TestRequestStub();
        $request->setQueryParam(null);
        $request->setHeaderValue('en;q=0.8, fr;q=0.9');
        $cookies = new TestCookiesStub();
        $locale = $this->makeRecordingLocale();

        $filter = $this->makeFilter($request, $cookies, $locale);
        $filter->onBegin(new RequestBegin($request));

        $this->assertSame(1, $locale->setCount);
        $this->assertSame('fr', $locale->setValue);
    }

    public function testOnBeginSkipsAcceptLanguageEntriesWithZeroQuality(): void
    {
        $request = new TestRequestStub();
        $request->setQueryParam(null);
        $request->setHeaderValue('fr;q=0, en;q=1');
        $cookies = new TestCookiesStub();
        $locale = $this->makeRecordingLocale();

        $filter = $this->makeFilter($request, $cookies, $locale);
        $filter->onBegin(new RequestBegin($request));

        $this->assertSame(1, $locale->setCount);
        $this->assertSame('en', $locale->setValue);
    }

    public function testOnBeginAppliesAliasesBeforeSupportedMatching(): void
    {
        $request = new TestRequestStub();
        $request->setQueryParam('zh');
        $cookies = new TestCookiesStub();
        $locale = $this->makeRecordingLocale();

        $filter = $this->makeFilter($request, $cookies, $locale, [
            'aliases' => ['zh' => 'zh-cn'],
            'supported' => ['zh-cn', 'en'],
        ]);
        $filter->onBegin(new RequestBegin($request));

        $this->assertSame(1, $locale->setCount);
        $this->assertSame('zh-cn', $locale->setValue);
    }

    public function testOnBeginDoesNotSetLocaleWhenNothingMatchesSupportedList(): void
    {
        $request = new TestRequestStub();
        $request->setQueryParam('xx');
        $cookies = new TestCookiesStub();
        $locale = $this->makeRecordingLocale();

        $filter = $this->makeFilter($request, $cookies, $locale, ['supported' => ['en', 'fr']]);
        $filter->onBegin(new RequestBegin($request));

        $this->assertSame(0, $locale->setCount);
    }

    public function testOnBeginIgnoresMalformedAcceptLanguageTokens(): void
    {
        $request = new TestRequestStub();
        $request->setQueryParam(null);
        $request->setHeaderValue('not_a_locale, en');
        $cookies = new TestCookiesStub();
        $locale = $this->makeRecordingLocale();

        $filter = $this->makeFilter($request, $cookies, $locale);
        $filter->onBegin(new RequestBegin($request));

        $this->assertSame(1, $locale->setCount);
        $this->assertSame('en', $locale->setValue);
    }
}

final class RecordingLocale implements LocaleInterface
{
    public string $default = 'en';

    public ?string $setValue = null;

    public int $setCount = 0;

    public function get(): string
    {
        return $this->setValue ?? $this->default;
    }

    public function set(string $locale): static
    {
        ++$this->setCount;
        $this->setValue = $locale;

        return $this;
    }

    public function getDefault(): string
    {
        return $this->default;
    }
}
