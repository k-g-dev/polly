<?php

namespace App\Tests\TestSupport\Trait;

use App\EventSubscriber\LocaleSubscriber;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

trait LocaleManagementTrait
{
    use ParameterAccessTrait;

    private const NOT_SUPPORTED_LOCALE = 'xx';

    private static array $cachedData = [];

    private ?UrlGeneratorInterface $urlGenerator = null;

    public static function enabledLocalesProvider(): \Generator
    {
        foreach (self::getEnabledLocales() as $locale) {
            yield "Locale {$locale}" => [$locale];
        }
    }

    protected static function getDefaultLocale(): string
    {
        return self::getParameter('kernel.default_locale');
    }

    protected static function getEnabledLocales(): array
    {
        return self::getParameter('app.enabled_locales');
    }

    protected static function areNonDefaultLocalesAvailable(): bool
    {
        $key = 'are_non_default_locales_available';

        if (self::isCached($key)) {
            return self::$cachedData[$key];
        }

        $localeKeyedEnabledLocales = array_flip(self::getEnabledLocales());

        unset($localeKeyedEnabledLocales[self::getDefaultLocale()]);

        self::$cachedData[$key] = !empty($localeKeyedEnabledLocales);

        return self::$cachedData[$key];
    }

    protected static function getFirstNonDefaultEnabledLocale(): ?string
    {
        $key = 'first_non_default_enabled_locale';

        if (self::isCached($key)) {
            return self::$cachedData[$key];
        }

        $enabledLocales = self::getEnabledLocales();
        $uniqueLocales = array_unique($enabledLocales);

        $defaultLocaleKey = array_search(self::getDefaultLocale(), $uniqueLocales, true);

        if ($defaultLocaleKey !== false) {
             unset($uniqueLocales[$defaultLocaleKey]);
        }

        self::$cachedData[$key] = current($uniqueLocales) ?: null;

        return self::$cachedData[$key];
    }

    protected static function getNotSupportedLocaleLabel(): string
    {
        return self::NOT_SUPPORTED_LOCALE . ' (not supported)';
    }

    protected static function isLocaleSupported(string $locale): bool
    {
        return in_array($locale, self::getEnabledLocales(), true);
    }

    /**
     * Allows creating a path even for invalid locations to use them during testing.
     *
     * @param string $basePath Path without locale prefix.
     * @param string $locale
     */
    protected static function assembleLocalizedPath(string $basePath, string $locale = null): string
    {
        $locale ??= self::getDefaultLocale();

        $cleanLocale = trim($locale, '/');
        $cleanBasePath = ltrim($basePath, '/');

        $segments = [];

        if ($cleanLocale !== self::getDefaultLocale()) {
            $segments[] = $cleanLocale;
        }

        if ($cleanBasePath !== '') {
            $segments[] = $cleanBasePath;
        }

        return '/' . implode('/', $segments);
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected static function getLinkFromLocaleSwitcher(Crawler $crawler, string $locale): ?Link
    {
        $xPath = sprintf(
            '//a[text()="%s" and contains(@href, "%s=1")]',
            $locale,
            LocaleSubscriber::QUERY_PARAM_IS_LOCALE_CHANGED,
        );

        $filtered = $crawler->filterXPath($xPath);

        return $filtered->count() ? $filtered->link() : null;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected static function getAllLinksFromLocaleSwitcher(Crawler $crawler): array
    {
        $xPath = sprintf(
            '//a[contains(@href, "%s=1")]',
            LocaleSubscriber::QUERY_PARAM_IS_LOCALE_CHANGED,
        );

        return $crawler->filterXPath($xPath)->links();
    }

    private static function isCached(string $key): bool
    {
        return array_key_exists($key, self::$cachedData);
    }

    /**
     * @throws RouteNotFoundException
     * @throws MissingMandatoryParametersException
     * @throws InvalidParameterException
     */
    protected function getLocalizedRouteUrl(string $routeName, string $locale, array $params = []): string
    {
        $params['_locale'] = $locale;

        $this->urlGeneratorInit();

        return $this->urlGenerator->generate($routeName, $params);
    }

    private function urlGeneratorInit(): void
    {
        if (null === $this->urlGenerator) {
            $this->urlGenerator = static::getContainer()->get(UrlGeneratorInterface::class);
        }
    }
}
