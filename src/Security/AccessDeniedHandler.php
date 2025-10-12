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

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'app.security.access_control.restricted_routes')]
        private array $restrictedRoutes,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        $routeConfig = $this->findRestrictedRouteConfig($request);

        $this->requestStack->getSession()->getFlashBag()
            ->add(FlashMessageType::Danger->value, $routeConfig['message'] ?? 'Access denied.');

        return new RedirectResponse(
            $this->urlGenerator->generate($routeConfig['redirect_to_route'] ?? MainController::ROUTE_HOMEPAGE),
        );
    }

    private function findRestrictedRouteConfig(Request $request): ?array
    {
        $route = $request->attributes->get('_route');

        if (!$route) {
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
