<?php

namespace App\Security;

use App\Enum\AuthorizationRole;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * @see https://symfony.com/doc/current/security/custom_authenticator.html
 */
class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(private RouterInterface $router)
    {
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->router->generate('app_login');
    }

    public function authenticate(Request $request): Passport
    {
        $payload = $request->getPayload()->all();

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $payload['_username']);

        return new Passport(
            new UserBadge($payload['_username']),
            new PasswordCredentials($payload['_password']),
            [new CsrfTokenBadge('authenticate', $payload['_csrf_token'])],
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);

        if ($targetPath) {
            // Let the request continue.
            return null;
        }

        $targetRouteName = (
            in_array(AuthorizationRole::Admin->value, $token->getRoleNames(), true)
            ? AuthorizationRole::Admin
            : AuthorizationRole::User
        )
        ->getRouteNameToRedirectAfterLogin();

        return new RedirectResponse(
            $this->router->generate($targetRouteName),
        );
    }
}
