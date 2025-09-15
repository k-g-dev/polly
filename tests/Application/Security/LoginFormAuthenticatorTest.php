<?php

namespace App\Tests\Application\Security;

use App\Controller\AccountController;
use App\Controller\Admin\DashboardController;
use App\Controller\Auth\SecurityController;
use App\Enum\AuthorizationRole;
use App\Factory\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class LoginFormAuthenticatorTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    #[DataProvider('loginCasesProvider')]
    public function testOnAuthenticationSuccessRedirectsProperly(
        string $expectedTargetRoute,
        string $initialRoute,
        AuthorizationRole $authorizationRole,
    ): void {
        $user = UserFactory::createOne([
            'isVerified' => true,
            'roles' => $authorizationRole,
        ]);

        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = $this->client->getContainer()->get(UrlGeneratorInterface::class);

        $this->client->request('GET', $urlGenerator->generate($initialRoute));

        if ($initialRoute !== SecurityController::ROUTE_LOGIN) {
            $this->client->followRedirect();
            self::assertRouteSame(SecurityController::ROUTE_LOGIN);
        }

        $this->client->submitForm('Sign in', [
            '_username' => $user->getUserIdentifier(),
            '_password' => UserFactory::USER_DEFAULT_PASSWORD,
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertRouteSame($expectedTargetRoute);
    }

    public static function loginCasesProvider(): \Generator
    {
        yield 'User with initial request to login page' => [
            'expectedTargetRoute' => AuthorizationRole::User->getRouteNameToRedirectAfterLogin(),
            'initialRoute' => SecurityController::ROUTE_LOGIN,
            'authorizationRole' => AuthorizationRole::User,
        ];

        yield 'Admin with initial request to login page' => [
            'expectedTargetRoute' =>  AuthorizationRole::Admin->getRouteNameToRedirectAfterLogin(),
            'initialRoute' => SecurityController::ROUTE_LOGIN,
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
