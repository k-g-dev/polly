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
use Symfony\Contracts\Translation\TranslatorInterface;
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
    private TranslatorInterface $translator;

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
        $this->translator = static::getContainer()->get(TranslatorInterface::class);

        $this->resetRateLimiter();
    }

    public function testLoginPageLoadsSuccessfully(): void
    {
        $crawler = $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertPageTitleContains($this->translator->trans('auth.security.login.title', domain: 'sites'));
        self::assertSelectorTextSame('h1', $this->translator->trans('auth.security.login.heading', domain: 'sites'));

        $form = $crawler
            ->selectButton($this->translator->trans('form.login.button.submit', domain: 'forms'))
            ->form();

        foreach (self::LOGIN_FORM_FIELD_NAMES as $fieldName) {
            self::assertTrue($form->has($fieldName), "The \"{$fieldName}\" field not exist in login form.");
        }
    }

    #[DataProvider('userInvalidCredentialsProvider')]
    public function testCantLoginWithInvalidCredentials(array $userAttributes, array $badCredentials): void
    {
        UserFactory::createOne($userAttributes);

        $this->client->request('GET', '/login');

        $this->client->submitForm($this->translator->trans('form.login.button.submit', domain: 'forms'), [
            '_username' => $badCredentials['email'] ?? $userAttributes['email'],
            '_password' => $badCredentials['password'] ?? $userAttributes['password'],
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();

        // Ensure we do not reveal if the user exists or not.
        self::assertSelectorTextContains(
            '.alert-danger',
            $this->translator->trans('Invalid credentials.', domain: 'security'),
        );
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

        $this->client->submitForm($this->translator->trans('form.login.button.submit', domain: 'forms'), [
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
        $form = $crawler
            ->selectButton($this->translator->trans('form.login.button.submit', domain: 'forms'))
            ->form();

        $form->setValues([
            '_username' => 'doesNotExist@example.com',
            '_password' => 'wrongPassword',
        ]);

        // Simulate allowed number of login attempts.
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->client->submit($form);

            self::assertResponseRedirects();
            $this->client->followRedirect();
            self::assertRouteSame(SecurityController::ROUTE_LOGIN);

            self::assertSelectorTextSame(
                '.alert-danger',
                $this->translator->trans('Invalid credentials.', domain: 'security'),
            );
        }

        // Simulate another one login attemt that exceed max allowed attempts.
        $this->client->submit($form);

        $error = $this->client
            ->getRequest()
            ->getSession()
            ->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);

        $errorMessage = $this->translator->trans($error->getMessageKey(), $error->getMessageData(), 'security');

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertRouteSame(SecurityController::ROUTE_LOGIN);

        self::assertSelectorTextSame('.alert-danger', $errorMessage);
    }

    public function testLoginFormIsNotDisplayedToLoggedInUser(): void
    {
        /** @var User $user */
        $user = UserFactory::createOne(['isVerified' => true]);
        $this->client->loginUser($user->_real());

        $crawler = $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame(
            '.alert-warning',
            sprintf(
                '%s: %s',
                $this->translator->trans('auth.security.login.logged_in_as', domain: 'sites'),
                $user->getUserIdentifier(),
            ),
        );

        self::assertEmpty(
            $crawler
                ->selectButton($this->translator->trans('form.login.button.submit', domain: 'forms')),
        );
        self::assertNotEmpty(
            $crawler
                ->filter('main')
                ->selectLink($this->translator->trans('action.auth.logout', domain: 'messages')),
        );
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
