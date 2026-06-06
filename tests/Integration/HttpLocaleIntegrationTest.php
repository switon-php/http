<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Integration;

use Switon\Core\LocaleInterface;
use Switon\Core\TranslatorInterface;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Http\CookiesInterface;
use Switon\Http\Event\RequestBegin;
use Switon\Http\Filter\RequestLocaleFilter;
use Switon\Http\RequestInterface;
use Switon\Http\Tests\Fixtures\TestCookiesStub;
use Switon\Http\Tests\Fixtures\TestRequestStub;
use Switon\Http\Tests\TestCase;
use Switon\I18n\Locale;
use Switon\I18n\Translator;

use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

class HttpLocaleIntegrationTest extends TestCase
{
    protected string $i18nDir;

    protected function setUp(): void
    {
        parent::setUp();

        $pathAlias = $this->container->get(\Switon\Core\PathAliasInterface::class);
        $this->i18nDir = $pathAlias->get('@i18n') ?? sys_get_temp_dir() . '/switon_http_i18n_' . uniqid('', true);
        $pathAlias->set('@i18n', $this->i18nDir);
        @mkdir($this->i18nDir, 0755, true);

        $this->container->set(LocaleInterface::class, [
            'class' => Locale::class,
            'default' => 'en',
        ]);
        $this->container->set(TranslatorInterface::class, [
            'class' => Translator::class,
            'dirs' => ['@i18n'],
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->i18nDir) && is_dir($this->i18nDir)) {
            foreach (glob($this->i18nDir . '/*.php') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->i18nDir);
        }

        parent::tearDown();
    }

    protected function createTranslationFile(string $locale, array $translations): void
    {
        $file = $this->i18nDir . '/' . $locale . '.php';
        $content = "<?php\n\nreturn " . var_export($translations, true) . ";\n";
        file_put_contents($file, $content);
    }

    protected function registerLocaleFilter(TestRequestStub $request, TestCookiesStub $cookies, array $config = []): ListenerProviderInterface
    {
        $this->container->set(RequestInterface::class, $request);
        $this->container->set(CookiesInterface::class, $cookies);
        $this->container->replace(RequestLocaleFilter::class, array_merge([
            'class' => RequestLocaleFilter::class,
        ], $config));

        $listenerProvider = $this->container->get(ListenerProviderInterface::class);
        $listenerProvider->register(RequestLocaleFilter::class);
        return $listenerProvider;
    }

    protected function triggerRequestBegin(ListenerProviderInterface $listenerProvider, TestRequestStub $request): void
    {
        $event = new RequestBegin($request);
        $listeners = iterator_to_array($listenerProvider->getListenersForEvent($event));
        $this->assertNotEmpty($listeners);
        foreach ($listeners as $listener) {
            $listener($event);
        }
    }

    public function testQueryLocaleDrivesTranslatorOutput(): void
    {
        $this->createTranslationFile('en', ['welcome' => 'Welcome']);
        $this->createTranslationFile('fr', ['welcome' => 'Bonjour']);

        $request = new TestRequestStub();
        $request->setQueryParam('fr');
        $cookies = new TestCookiesStub();
        $listenerProvider = $this->registerLocaleFilter($request, $cookies);
        $locale = $this->container->get(LocaleInterface::class);
        $translator = $this->container->get(TranslatorInterface::class);

        $this->triggerRequestBegin($listenerProvider, $request);
        $this->assertSame('fr', $locale->get());
        $this->assertSame('Bonjour', $translator->translate('welcome'));
    }

    public function testCookieLocaleUsedWhenQueryMissing(): void
    {
        $this->createTranslationFile('en', ['welcome' => 'Welcome']);
        $this->createTranslationFile('de', ['welcome' => 'Guten Tag']);

        $request = new TestRequestStub();
        $request->setQueryParam(null);
        $cookies = new TestCookiesStub();
        $cookies->setCookie('locale', 'de');
        $listenerProvider = $this->registerLocaleFilter($request, $cookies);
        $locale = $this->container->get(LocaleInterface::class);
        $translator = $this->container->get(TranslatorInterface::class);

        $this->triggerRequestBegin($listenerProvider, $request);
        $this->assertSame('de', $locale->get());
        $this->assertSame('Guten Tag', $translator->translate('welcome'));
    }

    public function testAcceptLanguageHeaderUsedWhenQueryAndCookieMissing(): void
    {
        $this->createTranslationFile('en', ['welcome' => 'Welcome']);
        $this->createTranslationFile('ja-jp', ['welcome' => 'Konnichiwa']);

        $request = new TestRequestStub();
        $request->setQueryParam(null);
        $request->setHeaderValue('ja-JP,en;q=0.5');
        $cookies = new TestCookiesStub();
        $listenerProvider = $this->registerLocaleFilter($request, $cookies);
        $locale = $this->container->get(LocaleInterface::class);
        $translator = $this->container->get(TranslatorInterface::class);

        $this->triggerRequestBegin($listenerProvider, $request);
        $this->assertSame('ja-jp', $locale->get());
        $this->assertSame('Konnichiwa', $translator->translate('welcome'));
    }

    public function testAcceptLanguagePicksHigherQualityTag(): void
    {
        $this->createTranslationFile('en', ['welcome' => 'Welcome']);
        $this->createTranslationFile('fr', ['welcome' => 'Bonjour']);

        $request = new TestRequestStub();
        $request->setQueryParam(null);
        $request->setHeaderValue('en;q=0.4, fr-CH;q=0.9');
        $cookies = new TestCookiesStub();
        $listenerProvider = $this->registerLocaleFilter($request, $cookies);
        $locale = $this->container->get(LocaleInterface::class);

        $this->triggerRequestBegin($listenerProvider, $request);
        $this->assertSame('fr-ch', $locale->get());
    }

    public function testAcceptLanguageSkipsZeroQualityAndFallsBack(): void
    {
        $this->createTranslationFile('en', ['welcome' => 'Welcome']);
        $this->createTranslationFile('es', ['welcome' => 'Hola']);

        $request = new TestRequestStub();
        $request->setQueryParam(null);
        $request->setHeaderValue('en;q=0, es');
        $cookies = new TestCookiesStub();
        $listenerProvider = $this->registerLocaleFilter($request, $cookies);
        $locale = $this->container->get(LocaleInterface::class);

        $this->triggerRequestBegin($listenerProvider, $request);
        $this->assertSame('es', $locale->get());
    }

    public function testSupportedListRestrictsQueryLocale(): void
    {
        $this->createTranslationFile('en', ['welcome' => 'Welcome']);
        $this->createTranslationFile('de', ['welcome' => 'Guten Tag']);

        $request = new TestRequestStub();
        $request->setQueryParam('de');
        $cookies = new TestCookiesStub();
        $cookies->setCookie('locale', 'en');
        $listenerProvider = $this->registerLocaleFilter($request, $cookies, [
            'supported' => ['en'],
        ]);
        $locale = $this->container->get(LocaleInterface::class);

        $this->triggerRequestBegin($listenerProvider, $request);
        $this->assertSame('en', $locale->get());
    }

    public function testAliasMapsQueryLocaleBeforeSupportedMatch(): void
    {
        $this->createTranslationFile('zh-cn', ['welcome' => '你好']);

        $request = new TestRequestStub();
        $request->setQueryParam('zh');
        $cookies = new TestCookiesStub();
        $listenerProvider = $this->registerLocaleFilter($request, $cookies, [
            'supported' => ['zh-cn'],
            'aliases' => ['zh' => 'zh-cn'],
        ]);
        $locale = $this->container->get(LocaleInterface::class);
        $translator = $this->container->get(TranslatorInterface::class);

        $this->triggerRequestBegin($listenerProvider, $request);
        $this->assertSame('zh-cn', $locale->get());
        $this->assertSame('你好', $translator->translate('welcome'));
    }

    public function testWhitespaceOnlyQueryFallsThroughToCookie(): void
    {
        $this->createTranslationFile('en', ['welcome' => 'Welcome']);
        $this->createTranslationFile('it', ['welcome' => 'Ciao']);

        $request = new TestRequestStub();
        $request->setQueryParam('   ');
        $cookies = new TestCookiesStub();
        $cookies->setCookie('locale', 'it');
        $listenerProvider = $this->registerLocaleFilter($request, $cookies);
        $locale = $this->container->get(LocaleInterface::class);

        $this->triggerRequestBegin($listenerProvider, $request);
        $this->assertSame('it', $locale->get());
    }

    public function testSupportedListMatchesLanguageSubtagWhenRegionNotListed(): void
    {
        $this->createTranslationFile('en', ['welcome' => 'Welcome']);

        $request = new TestRequestStub();
        $request->setQueryParam('en-GB');
        $cookies = new TestCookiesStub();
        $listenerProvider = $this->registerLocaleFilter($request, $cookies, [
            'supported' => ['en'],
        ]);
        $locale = $this->container->get(LocaleInterface::class);
        $translator = $this->container->get(TranslatorInterface::class);

        $this->triggerRequestBegin($listenerProvider, $request);
        $this->assertSame('en', $locale->get());
        $this->assertSame('Welcome', $translator->translate('welcome'));
    }
}
