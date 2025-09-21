<?php

namespace App\Tests\Application\Controller;

use App\Const\Common;
use App\Controller\AccountController;
use App\Entity\User;
use App\Factory\UserFactory;
use App\Helper\ArrayHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\force;

final class AccountControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private const PASSWORD_CHANGE_FORM_FIELDS = [
        'csrfToken' => 'password_change_form[_token]',
        'oldPassword' => 'password_change_form[oldPassword]',
        'newPassword' => [
            'first' => 'password_change_form[newPassword][first]',
            'second' => 'password_change_form[newPassword][second]',
        ],
    ];

    private const PASSWORD_CHANGE_FORM_SUBMIT_BUTTON_TEXT = 'Submit';

    private static ArrayHelper $arrayHelper;

    private KernelBrowser $client;

    public static function setUpBeforeClass(): void
    {
        self::$arrayHelper = static::getContainer()->get(ArrayHelper::class);
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testUserHasAccessToAccountSection(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user->_real());

        $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();
    }

    public function testAnonymousUserDoesNotHaveAccessToAccountSection(): void
    {
        $this->client->request('GET', '/account');
        self::assertResponseRedirects('/login', 302);
    }

    public function testTermsOfServiceAcceptance(): void
    {
        $user = UserFactory::createOne(['agreedTermsAt' => force(null)]);
        $this->client->loginUser($user->_real());

        self::assertFalse($user->hasAgreedToTerms());

        $this->client->request('GET', '/account');

        $initialTargetUrl = $this->client->getRequest()->getUri();

        /** @var Session $sessionBeforeAcceptTerms */
        $sessionBeforeAcceptTerms = $this->client->getRequest()->getSession();
        self::assertSame($initialTargetUrl, $sessionBeforeAcceptTerms->get(Common::AGREE_TO_TERMS_TARGET_URL_AFTER));

        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertRouteSame(AccountController::ROUTE_TERMS_OF_SERVICE_ACCEPTANCE);

        $this->client->submitForm('Submit', [
            'agree_to_terms_form[agreeTerms]' => true,
        ]);

        self::assertResponseRedirects($initialTargetUrl);
        $this->client->followRedirect();

        self::assertTrue($user->hasAgreedToTerms());

        /** @var Session $sessionAfterAcceptTerms */
        $sessionAfterAcceptTerms = $this->client->getRequest()->getSession();
        self::assertFalse($sessionAfterAcceptTerms->has(Common::AGREE_TO_TERMS_TARGET_URL_AFTER));
    }

    public function testPasswordChangePageLoadSuccessfully(): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user->_real());

        $crawler = $this->client->request('GET', '/account/password/change');
        self::assertResponseIsSuccessful();
        self::assertPageTitleContains('Change password');
        self::assertSelectorTextSame('h1', 'Change password');

        $form = $crawler->selectButton(self::PASSWORD_CHANGE_FORM_SUBMIT_BUTTON_TEXT)->form();

        foreach (self::$arrayHelper->flatten(self::PASSWORD_CHANGE_FORM_FIELDS) as $fieldName) {
            self::assertTrue($form->has($fieldName), "The \"{$fieldName}\" field not exist in change password form.");
        }

        $passwordRequirementsBtn = $crawler->filter('form button[data-bs-target="#passwordRequirements"]');
        self::assertCount(1, $passwordRequirementsBtn, 'There is no button displaying the full password requirements.');
        self::assertStringContainsStringIgnoringCase('password requirements', $passwordRequirementsBtn->text());
    }

    public function testPasswordChange(): void
    {
        /** @var User $user */
        $user = UserFactory::createOne();
        $this->client->loginUser($user->_real());

        $this->client->request('GET', '/account/password/change');

        self::assertResponseIsSuccessful();

        $oldHashedPassword = $user->getPassword();
        $newPassword = UserFactory::USER_DEFAULT_PASSWORD . 'x';

        $this->client->submitForm(self::PASSWORD_CHANGE_FORM_SUBMIT_BUTTON_TEXT, [
            self::PASSWORD_CHANGE_FORM_FIELDS['oldPassword'] => UserFactory::USER_DEFAULT_PASSWORD,
            self::PASSWORD_CHANGE_FORM_FIELDS['newPassword']['first'] => $newPassword,
            self::PASSWORD_CHANGE_FORM_FIELDS['newPassword']['second'] => $newPassword,
        ]);

        self::assertNotSame($oldHashedPassword, $user->getPassword());

        self::assertResponseRedirects('/account');
        $this->client->followRedirect();

        self::assertSelectorExists('.alert-success');
    }

    #[DataProvider('invalidPasswordChangeFormDataProvider')]
    public function testPasswordChangeFailsWhenInvalidFormData(array $formData): void
    {
        $user = UserFactory::createOne();
        $this->client->loginUser($user->_real());

        $this->client->request('GET', '/account/password/change');

        $oldHashedPassword = $user->getPassword();

        $this->client->submitForm(self::PASSWORD_CHANGE_FORM_SUBMIT_BUTTON_TEXT, [
            self::PASSWORD_CHANGE_FORM_FIELDS['oldPassword'] => $formData['oldPassword'],
            self::PASSWORD_CHANGE_FORM_FIELDS['newPassword']['first'] => $formData['newPassword']['first'],
            self::PASSWORD_CHANGE_FORM_FIELDS['newPassword']['second'] => $formData['newPassword']['second'],
        ]);

        self::assertSame($oldHashedPassword, $user->getPassword());

        $this->assertResponseStatusCodeSame(422);
        self::assertAnySelectorTextNotContains('.invalid-feedback', UserFactory::USER_DEFAULT_PASSWORD);
    }

    public static function invalidPasswordChangeFormDataProvider(): \Generator
    {
        yield 'Empty current password' => [
            'formData' => [
                'oldPassword' => '',
                'newPassword' => [
                    'first' => UserFactory::USER_DEFAULT_PASSWORD . 'y',
                    'second' => UserFactory::USER_DEFAULT_PASSWORD . 'y'
                ],
            ],
        ];

        yield 'Invalid current password' => [
            'formData' => [
                'oldPassword' => UserFactory::USER_DEFAULT_PASSWORD . 'x',
                'newPassword' => [
                    'first' => UserFactory::USER_DEFAULT_PASSWORD . 'y',
                    'second' => UserFactory::USER_DEFAULT_PASSWORD . 'y'
                ],
            ],
        ];

        yield 'Empty new password' => [
            'formData' => [
                'oldPassword' => UserFactory::USER_DEFAULT_PASSWORD,
                'newPassword' => [
                    'first' => '',
                    'second' => UserFactory::USER_DEFAULT_PASSWORD . 'y'
                ],
            ],
        ];

        yield 'Empty new password repeat' => [
            'formData' => [
                'oldPassword' => UserFactory::USER_DEFAULT_PASSWORD,
                'newPassword' => [
                    'first' => UserFactory::USER_DEFAULT_PASSWORD . 'y',
                    'second' => '',
                ],
            ],
        ];

        yield 'New password repeat does not match the new password' => [
            'formData' => [
                'oldPassword' => UserFactory::USER_DEFAULT_PASSWORD,
                'newPassword' => [
                    'first' => UserFactory::USER_DEFAULT_PASSWORD . 'y',
                    'second' => '',
                ],
            ],
        ];

        yield 'Invalid new password' => [
            'formData' => [
                'oldPassword' => UserFactory::USER_DEFAULT_PASSWORD,
                'newPassword' => [
                    'first' => strtolower(UserFactory::USER_DEFAULT_PASSWORD . 'y'),
                    'second' => strtolower(UserFactory::USER_DEFAULT_PASSWORD . 'y'),
                ],
            ],
        ];

        yield 'New password the same as current' => [
            'formData' => [
                'oldPassword' => UserFactory::USER_DEFAULT_PASSWORD,
                'newPassword' => [
                    'first' => UserFactory::USER_DEFAULT_PASSWORD,
                    'second' => UserFactory::USER_DEFAULT_PASSWORD,
                ],
            ],
        ];
    }
}
