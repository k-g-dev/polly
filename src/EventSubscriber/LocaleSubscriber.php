<?php

namespace App\EventSubscriber;

use App\Const\CookieNames;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LocaleSubscriber implements EventSubscriberInterface
{
    public const QUERY_PARAM_IS_LOCALE_CHANGED = 'is_locale_changed';

    private const ATTRIBUTE_CLEAR_COOKIE = 'clear_' . CookieNames::USER_PREFERRED_LOCALE;

    private bool $isLocaleChanged = false;
    private ?string $localeFromCookie = null;
    private ?string $localeFromUrl = null;
    private string $targetLocale;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'app.cookies.lifetime.default')]
        private int $cookieLifetime,
        #[Autowire(param: 'kernel.default_locale')]
        private string $defaultLocale,
        #[Autowire(param: 'app.enabled_locales')]
        private array $enabledLocales = [],
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
            KernelEvents::RESPONSE => ['onKernelResponse', -20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isAppRoute($request)) {
            return;
        }

        $this->isLocaleChanged = $request->query->getBoolean(self::QUERY_PARAM_IS_LOCALE_CHANGED);

        $this->localeFromCookie = $request->cookies->get(CookieNames::USER_PREFERRED_LOCALE);
        $this->localeFromUrl = $request->attributes->get('_locale');

        if (!$this->isLocaleFromCookieValid()) {
            $request->attributes->set(self::ATTRIBUTE_CLEAR_COOKIE, true);
            $this->localeFromCookie = null;
        }

        $this->determineTargetLocale();

        if ($this->shouldSkipLocaleProcessing($request)) {
            return;
        }

        $event->setResponse($this->prepareResponse($request));

        return;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($event->getRequest()->attributes->get(self::ATTRIBUTE_CLEAR_COOKIE)) {
            $this->clearLocaleCookie($event->getResponse());
        }
    }

    private function isAppRoute(Request $request): bool
    {
        $route = $request->attributes->get('_route');

        return ($route !== null) && str_starts_with($route, 'app_');
    }

    private function isLocaleFromCookieValid(): bool
    {
        return ($this->localeFromCookie === null) || in_array($this->localeFromCookie, $this->enabledLocales, true);
    }

    private function isLocaleFromUrlValid(): bool
    {
        return $this->localeFromUrl !== null;
    }

    private function determineTargetLocale(): void
    {
        $this->targetLocale = $this->isLocaleChanged
            ? $this->localeFromUrl
            : ($this->localeFromCookie ?? $this->localeFromUrl ?? $this->defaultLocale);
    }

    private function shouldSkipLocaleProcessing(Request $request): bool
    {
        if ($this->isLocaleChanged) {
            return false;
        }

        $routeParams = $request->attributes->get('_route_params');

        return ($this->isLocaleFromUrlValid() && ($this->localeFromCookie === $this->localeFromUrl))
            || ($routeParams['_locale'] === $this->targetLocale);
    }

    private function prepareResponse(Request $request): RedirectResponse
    {
        $routeParams = $request->attributes->get('_route_params');

        $routeParams['_locale'] = $this->targetLocale;

        $queryParams = $request->query->all();

        unset($queryParams[self::QUERY_PARAM_IS_LOCALE_CHANGED]);

        $route = $request->attributes->get('_route');

        $url = $this->urlGenerator->generate($route, [...$routeParams, ...$queryParams]);

        $response = new RedirectResponse($url);

        if ($this->isLocaleChanged) {
            $request->attributes->remove(self::ATTRIBUTE_CLEAR_COOKIE);
            $this->setLocaleCookie($response, $this->targetLocale);
        }

        return $response;
    }

    private function setLocaleCookie(Response $response, string $locale): void
    {
        $cookie = Cookie::create(CookieNames::USER_PREFERRED_LOCALE, $locale, time() + $this->cookieLifetime);

        $response
            ->headers
            ->setCookie($cookie);
    }

    private function clearLocaleCookie(Response $response): void
    {
        $response
            ->headers
            ->clearCookie(CookieNames::USER_PREFERRED_LOCALE);
    }
}
