<?php

namespace App\Tests\Application\Controller\Auth;

use App\Const\Authentication;
use App\Controller\Auth\AccountVerificationController;
use App\Controller\Auth\SecurityController;
use App\Entity\User;
use App\Enum\AuthorizationRole;
use App\Factory\UserFactory;
use App\Tests\TestSupport\Trait\RateLimiterResetTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class SecurityControllerTest extends WebTestCase
{
    use Factories;
    use RateLimiterResetTrait;
    use ResetDatabase;

    private const CREDENTIALS = [
        'admin' => [
            'email' => 'admin@example.com',
            'password' => UserFactory::USER_DEFAULT_PASSWORD,
        ],
        'user' => [
            'email' => 'user@example.com',
            'password' => UserFactory::USER_DEFAULT_PASSWORD,
        ],
    ];

    private const LOGIN_FORM_FIELD_NAMES = [
        '_csrf_token',
        '_username',
        '_password',
    ];

    private KernelBrowser $client;

    private static function prepareUserAttributesBasedOnRole(
        AuthorizationRole $userRole = AuthorizationRole::User,
        array $overrides = [],
    ): array {
        return array_merge(
            self::CREDENTIALS[strtolower($userRole->name)],
            [
                'roles' => $userRole,
                'isVerified' => true,
            ],
            $overrides,
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->resetRateLimiter();
    }

    public function testLoginPageLoadsSuccessfully(): void
    {
        $crawler = $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Sign in');
        self::assertSelectorTextSame('h1', 'Sign in');

        $form = $crawler->selectButton('Sign in')->form();

        foreach (self::LOGIN_FORM_FIELD_NAMES as $fieldName) {
            self::assertTrue($form->has($fieldName), "The \"{$fieldName}\" field not exist in login form.");
        }
    }

    #[DataProvider('userInvalidCredentialsProvider')]
    public function testCantLoginWithInvalidCredentials(array $userAttributes, array $badCredentials): void
    {
        UserFactory::createOne($userAttributes);

        $this->client->request('GET', '/login');

        $this->client->submitForm('Sign in', [
            '_username' => $badCredentials['email'] ?? $userAttributes['email'],
            '_password' => $badCredentials['password'] ?? $userAttributes['password'],
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        // Ensure we do not reveal if the user exists or not.
        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials.');
    }

    public static function userInvalidCredentialsProvider(): \Generator
    {
        yield 'Invalid email' => [
            'userAttributes' => self::prepareUserAttributesBasedOnRole(AuthorizationRole::User),
            'badCredentials' => [
                'email' => 'doesNotExist@example.com',
            ]
        ];

        yield 'Invalid password' => [
            'userAttributes' => self::prepareUserAttributesBasedOnRole(AuthorizationRole::User),
            'badCredentials' => [
                'password' => 'wrongPassword',
            ]
        ];
    }

    #[DataProvider('userValidCredentialsProvider')]
    public function testSuccessfulLoginWithValidCredentials(array $userAttributes): void
    {
        UserFactory::createOne($userAttributes);

        $this->client->request('GET', '/login');

        $this->client->submitForm('Sign in', [
            '_username' => $userAttributes['email'],
            '_password' => $userAttributes['password'],
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertRouteSame($userAttributes['roles']->getRouteNameToRedirectAfterLogin());

        $authorizationChecker = self::getContainer()->get(AuthorizationCheckerInterface::class);
        self::assertTrue($authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY'));

        self::assertSelectorNotExists('.alert-danger');
        self::assertResponseIsSuccessful();
    }

    public static function userValidCredentialsProvider(): \Generator
    {
        yield 'Admin' => [
            'userAttributes' => self::prepareUserAttributesBasedOnRole(AuthorizationRole::Admin),
        ];

        yield 'User' => [
            'userAttributes' => self::prepareUserAttributesBasedOnRole(AuthorizationRole::User),
        ];
    }

    public function testLoginThrottlingIsEnabled(): void
    {
        $maxAttempts = self::getContainer()->getParameter('app.login_throttling.max_attempts');

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Sign in')->form();

        $form->setValues([
            '_username' => 'doesNotExist@example.com',
            '_password' => 'wrongPassword',
        ]);

        for ($i = 0;; $i++) {
            $this->client->submit($form);

            self::assertResponseRedirects();
            $this->client->followRedirect();
            self::assertRouteSame(SecurityController::ROUTE_LOGIN);

            if ($i < $maxAttempts) {
                self::assertSelectorTextSame('.alert-danger', 'Invalid credentials.');
                continue;
            }

            self::assertSelectorTextContains('.alert-danger', 'Too many failed login attempts');
            break;
        }
    }

    public function testLoginFormIsNotDisplayedToLoggedInUser(): void
    {
        $user = UserFactory::createOne(['isVerified' => true]);
        $this->client->loginUser($user->_real());

        $crawler = $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.alert-warning', "You are logged in as: {$user->_real()->getUserIdentifier()}");

        self::assertEmpty($crawler->selectButton('Sign in'));
        self::assertNotEmpty($crawler->filter('main')->selectLink('Sign out'));
    }

    public function testCantLoginToNotVerifiedAccount(): void
    {
        /** @var User $user */
        $user = UserFactory::createOne(['isVerified' => false]);

        $this->client->request('GET', '/login');

        $this->client->submitForm('Sign in', [
            '_username' => $user->getUserIdentifier(),
            '_password' => UserFactory::USER_DEFAULT_PASSWORD,
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertRouteSame(AccountVerificationController::ROUTE_RESEND_VERIFICATION_EMAIL);

        /** @var Session $session */
        $session = $this->client->getRequest()->getSession();

        self::assertSame($user->getEmail(), $session->get(Authentication::NON_VERIFIED_EMAIL));
        self::assertFalse($session->has(SecurityRequestAttributes::AUTHENTICATION_ERROR));
    }

    public function testLogoutRedirectsToHomepage(): void
    {
        $user = UserFactory::createOne(['isVerified' => true]);
        $this->client->loginUser($user->_real());

        $authorizationChecker = self::getContainer()->get(AuthorizationCheckerInterface::class);
        self::assertTrue($authorizationChecker->isGranted('IS_AUTHENTICATED'));

        $this->client->request('GET', '/terms-of-service');

        $this->client->request('GET', '/logout');
        self::assertResponseRedirects('/');

        self::assertFalse($authorizationChecker->isGranted('IS_AUTHENTICATED'));
    }
}
