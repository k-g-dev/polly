<?php

namespace App\Security;

use App\Controller\MainController;
use App\Enum\FlashMessageType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'app.security.access_control.restricted_routes')]
        private ?array $restrictedRoutes,
        private TranslatorInterface $translator,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        $routeConfig = $this->findRestrictedRouteConfig($request);

        // This is written this way so that at least the default translation key can be detected by translation tools
        // like debug:translation.
        $message = isset($routeConfig['message'])
            ? $this->translator->trans($routeConfig['message'], domain: 'security')
            : $this->translator->trans('access_control.access_denied.default', domain: 'security');

        $this->requestStack->getSession()
            ->getFlashBag()
            ->add(FlashMessageType::Danger->value, $message);

        return new RedirectResponse(
            $this->urlGenerator->generate($routeConfig['redirect_to_route'] ?? MainController::ROUTE_HOMEPAGE),
        );
    }

    private function findRestrictedRouteConfig(Request $request): ?array
    {
        $route = $request->attributes->get('_route');

        if (!$route || empty($this->restrictedRoutes)) {
            return null;
        }

        foreach ($this->restrictedRoutes as $restrictedRoutePattern => $config) {
            if (preg_match("#{$restrictedRoutePattern}#", $route)) {
                return $config;
            }
        }

        return null;
    }
}
