<?php

namespace App\Tests\Integration\Security;

use App\Controller\AccountController;
use App\Controller\Admin\DashboardController;
use App\Entity\User;
use App\Enum\AuthorizationRole;
use App\Security\LoginFormAuthenticator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class LoginFormAuthenticatorTest extends KernelTestCase
{
    #[DataProvider('loginCasesProvider')]
    public function testOnAuthenticationSuccessRedirectsProperly(
        string $expectedTargetRoute,
        AuthorizationRole $authorizationRole,
        ?string $initialRoute = null,
        string $firewallName = 'main',
    ): void {
        $container = static::getContainer();
        $urlGenerator = $container->get(UrlGeneratorInterface::class);

        $user = new User();
        $user->setRoles($authorizationRole);

        $session = $this->createStub(Session::class);
        if ($initialRoute !== null) {
            $session->method('get')
                ->with("_security.{$firewallName}.target_path")
                ->willReturn($urlGenerator->generate($initialRoute));
        }

        $request = $this->createStub(Request::class);
        $request->method('getSession')->willReturn($session);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getRoleNames')->willReturn($user->getRoles());

        $authenticator = $container->get(LoginFormAuthenticator::class);

        $response = $authenticator->onAuthenticationSuccess($request, $token, $firewallName);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($urlGenerator->generate($expectedTargetRoute), $response->getTargetUrl());
    }

    public static function loginCasesProvider(): \Generator
    {
        yield 'User directly from login page' => [
            'expectedTargetRoute' => AuthorizationRole::User->getRouteNameToRedirectAfterLogin(),
            'authorizationRole' => AuthorizationRole::User,
        ];

        yield 'Admin directly from login page' => [
            'expectedTargetRoute' =>  AuthorizationRole::Admin->getRouteNameToRedirectAfterLogin(),
            'authorizationRole' => AuthorizationRole::Admin,
        ];

        yield 'Admin with initial request to secured area' => [
            'expectedTargetRoute' => AccountController::ROUTE_INDEX,
            'initialRoute' => AccountController::ROUTE_INDEX,
            'authorizationRole' => AuthorizationRole::Admin,
        ];

        yield 'User with initial request to secured area' => [
            'expectedTargetRoute' => DashboardController::ROUTE_INDEX,
            'initialRoute' => DashboardController::ROUTE_INDEX,
            'authorizationRole' => AuthorizationRole::User,
        ];
    }
}
