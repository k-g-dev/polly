<?php

namespace App\Tests\TestSupport\Trait;

use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

trait LocaleManagementTrait
{
    private ?UrlGeneratorInterface $urlGenerator = null;

    public static function enabledLocalesProvider(): \Generator
    {
        foreach (static::getEnabledLocales() as $locale) {
            yield "Locale {$locale}" => [$locale];
        }
    }

    protected static function getEnabledLocales(): array
    {
        return static::getContainer()->getParameter('app.enabled_locales');
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
