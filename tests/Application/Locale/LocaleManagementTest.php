<?php

namespace App\Tests\Locale;

use App\Const\CookieNames;
use App\Controller\MainController;
use App\Tests\TestSupport\Trait\CookieAssertionsTrait;
use App\Tests\TestSupport\Trait\LocaleManagementTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class LocaleManagementTest extends WebTestCase
{
    use CookieAssertionsTrait;
    use LocaleManagementTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testDefaultLocaleExistInEnabledLocales(): void
    {
        self::assertTrue(
            in_array(self::getDefaultLocale(), self::getEnabledLocales(), true),
            'The default locale should exist in enabled locales.',
        );
    }


    public function testLocaleSwitcherNotDisplayIfNoEnabledLocales(): void
    {
        $this->skipIfNonDefaultLocalesAreSupported();

        $this->client->request(Request::METHOD_GET, '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(
            '.language-switcher',
            'The language switcher should not appear if no other locales are available.',
        );
    }

    #[Depends('testDefaultLocaleExistInEnabledLocales')]
    public function testLocaleSwitcherDisplayProperly(): void
    {
        $this->skipIfOnlyDefaultLocaleIsSupported();

        $crawler = $this->client->request(Request::METHOD_GET, '/');

        self::assertResponseIsSuccessful();

        $languageSwitcher = $crawler->filter('.language-switcher');

        self::assertCount(1, $languageSwitcher);

        self::assertEqualsIgnoringCase(
            self::getDefaultLocale(),
            $languageSwitcher->filter('.language-switcher__toggler')->text(),
        );

        // Subtract 1 from the enabled locales because the currently selected locale is not a link.
        self::assertCount(count(self::getEnabledLocales()) - 1, self::getAllLinksFromLocaleSwitcher($crawler));

        $targetLocale = self::getFirstNonDefaultEnabledLocale();

        $crawler = $this->client->request(Request::METHOD_GET, self::assembleLocalizedPath('/', $targetLocale));

        self::assertResponseIsSuccessful();

        self::assertEqualsIgnoringCase(
            $targetLocale,
            $crawler->filter('.language-switcher__toggler')->text(),
        );
    }

    #[Depends('testDefaultLocaleExistInEnabledLocales')]
    #[DataProvider('localeFromUrlProvider')]
    public function testNoActionIfLocaleLoadedDirectlyFromUrl(
        string $localeFromUrl,
        bool $shouldLocaleBeSupported = true,
    ): void {
        self::assertNull($this->client->getCookieJar()->get(CookieNames::USER_PREFERRED_LOCALE));

        $this->client->request(Request::METHOD_GET, self::assembleLocalizedPath('/', $localeFromUrl));

        self::assertResponseNotHasHeader('Set-Cookie');
        self::assertResponseNotHasCookie(CookieNames::USER_PREFERRED_LOCALE);

        $isLocaleSupported = self::isLocaleSupported($localeFromUrl);

        self::assertSame(
            $shouldLocaleBeSupported,
            $isLocaleSupported,
            sprintf('%s locale should %s be supported.', $localeFromUrl, $shouldLocaleBeSupported ? '' : 'not'),
        );

        $request = $this->client->getRequest();

        if ($isLocaleSupported) {
            self::assertResponseIsSuccessful();
            self::assertSame($localeFromUrl, $request->getLocale());
        } else {
            self::assertResponseStatusCodeSame(404);
            self::assertSame(self::getDefaultLocale(), $request->getLocale());
        }
    }

    public static function localeFromUrlProvider(): \Generator
    {
        yield self::getDefaultLocale() => [
            'localeFromUrl' => self::getDefaultLocale(),
        ];

        if (self::areNonDefaultLocalesAvailable()) {
            yield self::getFirstNonDefaultEnabledLocale() => [
                'localeFromUrl' => self::getFirstNonDefaultEnabledLocale(),
            ];
        }

        yield self::getNotSupportedLocaleLabel() => [
            'localeFromUrl' => self::NOT_SUPPORTED_LOCALE,
            'shouldLocaleBeSupported' => false,
        ];
    }

    public function testUrlIsNotModifiedWhenUserSwitchLocale(): void
    {
        $this->skipIfOnlyDefaultLocaleIsSupported();

        $path = '/terms-of-service?a=1&b=z';
        $targetLocale = self::getFirstNonDefaultEnabledLocale();

        $crawler = $this->client->request(Request::METHOD_GET, $path);

        self::assertResponseIsSuccessful();
        self::assertEquals(self::getDefaultLocale(), $this->client->getRequest()->getLocale());

        $link = self::getLinkFromLocaleSwitcher($crawler, $targetLocale);

        self::assertNotNull($link, "No link to {$targetLocale} locale found in locale switcher.");

        $this->client->click($link);

        self::assertResponseRedirects(self::assembleLocalizedPath($path, $targetLocale));

        $this->client->followRedirect();

        self::assertSame($targetLocale, $this->client->getRequest()->getLocale());
    }

    #[Depends('testDefaultLocaleExistInEnabledLocales')]
    #[DataProvider('localeChangeProvider')]
    public function testCreateCookieWhenUserSwitchLocale(
        string $localeFrom,
        string $localeTo,
        bool $isSkipped = false,
    ): void {
        if ($isSkipped) {
            self::markTestSkipped();
        }

        $crawler = $this->client->request(Request::METHOD_GET, self::assembleLocalizedPath('/', $localeFrom));

        self::assertNull($this->client->getCookieJar()->get(CookieNames::USER_PREFERRED_LOCALE));

        $localeBeforeChange = $this->client->getRequest()->getLocale();

        self::assertEquals($localeFrom, $localeBeforeChange);

        $link = self::getLinkFromLocaleSwitcher($crawler, $localeTo);

        self::assertNotNull($link, "No link to {$localeTo} locale found in locale switcher.");

        $this->client->click($link);

        self::assertResponseRedirects();

        self::assertResponseHasHeader('Set-Cookie');
        self::assertResponseHasCookie(CookieNames::USER_PREFERRED_LOCALE);
        self::assertResponseCookieValueSame(CookieNames::USER_PREFERRED_LOCALE, $localeTo);

        $this->client->followRedirect();

        self::assertResponseIsSuccessful();

        self::assertSame($localeTo, $this->client->getRequest()->getLocale());
    }

    public static function localeChangeProvider(): \Generator
    {
        $hasData = false;

        if (self::areNonDefaultLocalesAvailable()) {
            yield self::getDefaultLocale() . ' -> ' . self::getFirstNonDefaultEnabledLocale() => [
                'localeFrom' => self::getDefaultLocale(),
                'localeTo' => self::getFirstNonDefaultEnabledLocale(),
            ];

            $hasData = true;

            yield self::getFirstNonDefaultEnabledLocale() . ' -> ' . self::getDefaultLocale() => [
                'localeFrom' => self::getFirstNonDefaultEnabledLocale(),
                'localeTo' => self::getDefaultLocale(),
            ];
        }

        // An empty dataset provided by the data provider is not allowed. If there is no data to provide, return dummy
        // data with a flag to skip the test.
        if (!$hasData) {
            yield 'skip test' => [
                'localeFrom' => '',
                'localeTo' => '',
                'isSkipped' => true,
            ];
        }
    }

    #[DataProvider('localeLocadedFromCookieProvider')]
    public function testLocaleLoadedFromCookie(string $localeFromUrl, string $localeFromCookie): void
    {
        $this->client->getCookieJar()->set(new Cookie(CookieNames::USER_PREFERRED_LOCALE, $localeFromCookie));

        $this->client->request(Request::METHOD_GET, self::assembleLocalizedPath('/', $localeFromUrl));

        if ($localeFromUrl !== $localeFromCookie) {
            self::assertResponseRedirects("/{$localeFromCookie}");

            $this->client->followRedirect();
        } else {
            self::assertFalse($this->client->getResponse()->isRedirection());
        }

        self::assertSame($localeFromCookie, $this->client->getRequest()->getLocale());
    }

    public static function localeLocadedFromCookieProvider(): \Generator
    {
        $areNonDefaultLocalesAvailable = self::areNonDefaultLocalesAvailable();

        if ($areNonDefaultLocalesAvailable) {
            yield self::getDefaultLocale() . ' -> ' . self::getFirstNonDefaultEnabledLocale() => [
                'localeFromUrl' => self::getDefaultLocale(),
                'localeFromCookie' => self::getFirstNonDefaultEnabledLocale(),
            ];
        }

        yield self::getDefaultLocale() . ' -> ' . self::getDefaultLocale() => [
            'localeFromUrl' => self::getDefaultLocale(),
            'localeFromCookie' => self::getDefaultLocale(),
        ];

        if ($areNonDefaultLocalesAvailable) {
            yield self::getFirstNonDefaultEnabledLocale() . ' -> ' . self::getFirstNonDefaultEnabledLocale() => [
                'localeFromUrl' => self::getFirstNonDefaultEnabledLocale(),
                'localeFromCookie' => self::getFirstNonDefaultEnabledLocale(),
            ];
        }
    }

    #[DataProvider('notSupportedLocaleFromCookieProvider')]
    public function testNoLocaleChangedIfLocaleFromCookieIsNotSupported(
        string $localeFromUrl,
        string $localeFromCookie,
    ): void {
        $cookieLifetime = static::getContainer()->getParameter('app.cookies.lifetime.default');

        $this->client
            ->getCookieJar()
            ->set(new Cookie(CookieNames::USER_PREFERRED_LOCALE, $localeFromCookie, time() + $cookieLifetime));

        $this->client->request(Request::METHOD_GET, self::assembleLocalizedPath('/', $localeFromUrl));

        // The test requires the locale to be unsupported.
        self::assertFalse(
            $this->isLocaleSupported($localeFromCookie),
            "{$localeFromCookie} locale should not be supported.",
        );

        self::assertResponseIsSuccessful();
        self::assertRouteSame(MainController::ROUTE_HOMEPAGE);

        self::assertSame($localeFromUrl, $this->client->getRequest()->getLocale());
        self::assertCookieDeleted(CookieNames::USER_PREFERRED_LOCALE, $this->client);
    }

    public static function notSupportedLocaleFromCookieProvider(): \Generator
    {
        yield self::getDefaultLocale() . ' -> ' . self::getNotSupportedLocaleLabel() => [
            'localeFromUrl' => self::getDefaultLocale(),
            'localeFromCookie' => self::NOT_SUPPORTED_LOCALE,
        ];

        if (self::areNonDefaultLocalesAvailable()) {
            yield self::getFirstNonDefaultEnabledLocale() . ' -> ' . self::getNotSupportedLocaleLabel() => [
                'localeFromUrl' => self::getFirstNonDefaultEnabledLocale(),
                'localeFromCookie' => self::NOT_SUPPORTED_LOCALE,
            ];
        }
    }

    private function skipIfOnlyDefaultLocaleIsSupported(): void
    {
        if (!self::areNonDefaultLocalesAvailable()) {
            self::markTestSkipped('This test is intended to run if non-default locales are supported.');
        }
    }

    private function skipIfNonDefaultLocalesAreSupported(): void
    {
        if (self::areNonDefaultLocalesAvailable()) {
            self::markTestSkipped('This test is intended to run if only the default locale is supported.');
        }
    }
}
